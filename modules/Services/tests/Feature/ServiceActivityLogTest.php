<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Services\Models\Service;
use Modules\Services\Models\TrainingService;
use Modules\Services\Models\Visit;
use Modules\Support\Enums\ActivationStatus;
use Spatie\Activitylog\Models\Activity;
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
    $this->user->syncCenterPermissions($this->center, [
        CenterUserPermission::ManageTrainingServices,
        CenterUserPermission::ManageVisitServices,
    ]);
    $this->actingAs($this->user, 'center_user');

    $this->trainingUrl = "/api/v1/centers/{$this->center->id}/training-services";
    $this->trainingBody = fn (array $extra = []) => [
        'name' => ['ar' => 'تدريب', 'en' => 'Training'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'price_options' => [['duration' => 30, 'price' => 100]],
        ...$extra,
    ];

    $this->updates = fn (int $serviceableId, string $type): Collection => Activity::query()
        ->forSubject(Service::query()->where('serviceable_type', $type)->where('serviceable_id', $serviceableId)->sole())
        ->forEvent('updated')
        ->get();
});

test('an edit of only the Arabic name is logged with every locale', function (): void {
    $id = $this->postJson($this->trainingUrl, ($this->trainingBody)())->json('data.id');

    $this->putJson("{$this->trainingUrl}/{$id}", ($this->trainingBody)(['name' => ['ar' => 'جديد', 'en' => 'Training']]))->assertOk();

    $log = ($this->updates)($id, TrainingService::class)->sole();

    expect(array_keys($log->attribute_changes['attributes']))->toBe(['name'])
        ->and($log->attribute_changes['old']['name'])->toBe(['ar' => 'تدريب', 'en' => 'Training'])
        ->and($log->attribute_changes['attributes']['name'])->toBe(['ar' => 'جديد', 'en' => 'Training'])
        ->and($log->causer_id)->toBe($this->user->id);
});

test('a price-only edit is logged as one service update', function (): void {
    $id = $this->postJson($this->trainingUrl, ($this->trainingBody)())->json('data.id');

    $this->putJson("{$this->trainingUrl}/{$id}", ($this->trainingBody)([
        'price_options' => [['duration' => 30, 'price' => 250]],
    ]))->assertOk();

    $log = ($this->updates)($id, TrainingService::class)->sole();

    expect(array_keys($log->attribute_changes['attributes']))->toBe(['price_options'])
        ->and($log->attribute_changes['old']['price_options'][0]['price'])->toBe(100)
        ->and($log->attribute_changes['attributes']['price_options'][0]['price'])->toBe(250);
});

test('an unchanged edit writes no log', function (): void {
    $id = $this->postJson($this->trainingUrl, ($this->trainingBody)())->json('data.id');

    $this->putJson("{$this->trainingUrl}/{$id}", ($this->trainingBody)())->assertOk();

    expect(($this->updates)($id, TrainingService::class))->toBeEmpty();
});

test('type details and schedules of a visit are logged', function (): void {
    $url = "/api/v1/centers/{$this->center->id}/visits";
    $body = fn (array $extra = []) => [
        'name' => ['ar' => 'زيارة', 'en' => 'Visit'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'enter_type' => 'all_day',
        'price' => 150,
        'days' => [0, 2],
        ...$extra,
    ];
    $id = $this->postJson($url, $body())->json('data.id');

    $this->putJson("{$url}/{$id}", $body(['days' => [0, 2, 4], 'max_tickets_per_day' => 20]))->assertOk();

    $changes = ($this->updates)($id, Visit::class)->sole()->attribute_changes;

    expect(array_keys($changes['attributes']))->toEqualCanonicalizing(['details', 'schedules'])
        ->and($changes['attributes']['details']['max_tickets_per_day'])->toBe(20)
        ->and(array_column($changes['attributes']['schedules'], 'day_of_week'))->toBe([0, 2, 4]);
});
