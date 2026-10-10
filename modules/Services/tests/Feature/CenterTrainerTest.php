<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Trainer;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->url = "/api/v1/centers/{$this->center->id}/trainers";
    $this->body = fn (array $extra = []) => [
        'name' => 'Ahmed',
        'max_tickets_per_hour' => 4,
        'days' => [0, 2],
        'hours' => ['09:00', '12:00', '14:00', '17:00'],
        ...$extra,
    ];
    $this->createTrainingService = function (?int $centerId = null): int {
        return $this->actingAs($this->user, 'center_user')
            ->postJson('/api/v1/centers/'.($centerId ?? $this->center->id).'/training-services', [
                'name' => ['ar' => 'تدريب', 'en' => 'Training'],
                'description' => ['ar' => 'وصف'],
                'price_options' => [['duration' => 60, 'price' => 200]],
            ])->assertCreated()->json('data.id');
    };
});

test('guests and unassigned users are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, ($this->body)())->assertForbidden();
});

test('creates a trainer with schedules and training services', function (): void {
    $trainingServiceId = ($this->createTrainingService)();

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['training_service_ids' => [$trainingServiceId]]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ahmed')
        ->assertJsonPath('data.center_id', $this->center->id)
        ->assertJsonPath('data.max_tickets_per_hour', 4)
        ->assertJsonPath('data.days', [0, 2])
        ->assertJsonPath('data.hours', ['09:00', '12:00', '14:00', '17:00'])
        ->assertJsonPath('data.training_services.0.id', $trainingServiceId)
        ->assertJsonPath('data.training_services.0.name.en', 'Training');

    expect(Trainer::count())->toBe(1)
        ->and(Schedule::count())->toBe(4);
});

test('validates the body', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['name' => '', 'hours' => ['09:00'], 'days' => [9], 'max_tickets_per_hour' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'hours', 'days.0', 'max_tickets_per_hour']);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['hours' => ['12:00', '09:00']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours.1']);
});

test('rejects training services of another center', function (): void {
    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $foreignTrainingServiceId = ($this->createTrainingService)($other->id);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['training_service_ids' => [$foreignTrainingServiceId]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['training_service_ids.0']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $trainingServiceId = ($this->createTrainingService)();
    $id = $this->postJson($this->url, ($this->body)(['training_service_ids' => [$trainingServiceId]]))->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->body)([
        'name' => 'Sara',
        'days' => [5],
        'hours' => ['10:00', '11:00'],
    ]))->assertOk()
        ->assertJsonPath('data.name', 'Sara')
        ->assertJsonPath('data.max_tickets_per_hour', 4)
        ->assertJsonPath('data.days', [5])
        ->assertJsonPath('data.hours', ['10:00', '11:00'])
        ->assertJsonCount(1, 'data.training_services');

    $this->putJson("{$this->url}/{$id}", ($this->body)(['max_tickets_per_hour' => null, 'training_service_ids' => []]))
        ->assertOk()
        ->assertJsonPath('data.max_tickets_per_hour', null)
        ->assertJsonCount(0, 'data.training_services');

    $this->deleteJson("{$this->url}/{$id}")->assertNoContent();
    $this->getJson("{$this->url}/{$id}")->assertNotFound();
    expect(Schedule::count())->toBe(0);
});

test('cannot access a trainer of another center', function (): void {
    $trainer = TrainerFactory::new()->create();

    $this->actingAs($this->user, 'center_user');

    $this->getJson("{$this->url}/{$trainer->id}")->assertNotFound();
    $this->putJson("{$this->url}/{$trainer->id}", ($this->body)())->assertNotFound();
    $this->deleteJson("{$this->url}/{$trainer->id}")->assertNotFound();
    $this->getJson($this->url)->assertOk()->assertJsonCount(0, 'data');
});
