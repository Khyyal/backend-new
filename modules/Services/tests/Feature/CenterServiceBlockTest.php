<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ServiceBlock;
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
    $this->url = "/api/v1/centers/{$this->center->id}/service-blocks";

    $this->body = fn (array $extra = []) => [
        'reason' => 'Maintenance',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-05',
        'time_scope' => 'all_day',
        ...$extra,
    ];

    $this->service = Resort::query()->create()->service()->create([
        'name' => ['ar' => 'منتجع'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $this->center->id,
        'status' => ActivationStatus::ACTIVE,
        'type' => ServiceType::Resort,
    ]);
});

test('guests and unassigned users are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, ($this->body)())->assertForbidden();
});

test('creates a block for all services', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)())
        ->assertCreated()
        ->assertJsonPath('data.service_id', null)
        ->assertJsonPath('data.reason', 'Maintenance')
        ->assertJsonPath('data.start_date', '2026-01-01')
        ->assertJsonPath('data.end_date', '2026-01-05')
        ->assertJsonPath('data.time_scope', 'all_day');

    expect(ServiceBlock::count())->toBe(1);
});

test('creates a block for a single specific service', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['service_id' => $this->service->id]))
        ->assertCreated()
        ->assertJsonPath('data.service_id', $this->service->id);
});

test('creates a single-date, specific-hours block with one period', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-01',
            'time_scope' => 'specific_time',
            'hours' => ['09:00', '12:00'],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.start_date', '2026-02-01')
        ->assertJsonPath('data.end_date', '2026-02-01')
        ->assertJsonPath('data.time_scope', 'specific_time')
        ->assertJsonPath('data.hours', ['09:00', '12:00']);
});

test('creates a specific-hours block with two disjoint periods', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([
            'time_scope' => 'specific_time',
            'hours' => ['12:00', '13:00', '19:00', '20:00'],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.time_scope', 'specific_time')
        ->assertJsonPath('data.hours', ['12:00', '13:00', '19:00', '20:00']);
});

test('validates the body', function (): void {
    $this->actingAs($this->user, 'center_user');

    // end_date before start_date
    $this->postJson($this->url, ($this->body)(['start_date' => '2026-01-10', 'end_date' => '2026-01-05']))
        ->assertUnprocessable()->assertJsonValidationErrors(['end_date']);

    // specific_time requires hours
    $this->postJson($this->url, ($this->body)(['time_scope' => 'specific_time']))
        ->assertUnprocessable()->assertJsonValidationErrors(['hours']);

    // all_day forbids hours
    $this->postJson($this->url, ($this->body)(['hours' => ['09:00', '12:00']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['hours']);

    // odd-count hours array
    $this->postJson($this->url, ($this->body)(['time_scope' => 'specific_time', 'hours' => ['09:00', '12:00', '14:00']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['hours']);

    // end must be after start, per pair
    $this->postJson($this->url, ($this->body)([
        'time_scope' => 'specific_time', 'hours' => ['12:00', '09:00'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['hours.1']);

    // service_id must belong to this center
    $other = CenterFactory::new()->create();
    $otherService = Resort::query()->create()->service()->create([
        'name' => ['ar' => 'آخر'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $other->id,
        'status' => ActivationStatus::ACTIVE,
        'type' => ServiceType::Resort,
    ]);
    $this->postJson($this->url, ($this->body)(['service_id' => $otherService->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['service_id']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->body)(['reason' => 'Updated reason']))
        ->assertOk()
        ->assertJsonPath('data.reason', 'Updated reason');

    $this->deleteJson("{$this->url}/{$id}")->assertNoContent();
    $this->getJson("{$this->url}/{$id}")->assertNotFound();
});

test('filters the index by service_id', function (): void {
    $this->actingAs($this->user, 'center_user');
    $this->postJson($this->url, ($this->body)())->assertCreated();
    $this->postJson($this->url, ($this->body)(['service_id' => $this->service->id]))->assertCreated();

    $this->getJson("{$this->url}?service_id={$this->service->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.service_id', $this->service->id);
});

test('cannot access a block of another center', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);

    $this->getJson("/api/v1/centers/{$other->id}/service-blocks/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/centers/{$other->id}/service-blocks/{$id}")->assertNotFound();
});
