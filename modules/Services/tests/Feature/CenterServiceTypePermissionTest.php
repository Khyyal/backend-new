<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
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
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageVisitServices]);
    $this->base = "/api/v1/centers/{$this->center->id}";

    $this->resort = Resort::query()->create()->service()->create([
        'name' => ['ar' => 'منتجع'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $this->center->id,
        'status' => ActivationStatus::ACTIVE,
        'type' => ServiceType::Resort,
    ]);

    $this->block = fn (array $extra = []): array => [
        'reason' => 'Maintenance',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-05',
        'time_scope' => 'all_day',
        ...$extra,
    ];
});

test('a service type permission only opens that type', function (): void {
    $this->actingAs($this->user, 'center_user')->getJson("{$this->base}/visits")->assertOk();
    $this->actingAs($this->user, 'center_user')->getJson("{$this->base}/resorts")->assertForbidden();
    $this->actingAs($this->user, 'center_user')->getJson("{$this->base}/trainers")->assertForbidden();

    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->base}/service-types/resort/terms", ['terms' => ['ar' => 'شروط']])
        ->assertForbidden();

    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->base}/service-types/visit/terms", ['terms' => ['ar' => 'شروط']])
        ->assertSuccessful();
});

test('a block on a service needs that service type', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->base}/service-blocks", ($this->block)(['service_id' => $this->resort->id]))
        ->assertForbidden();

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageResortServices]);

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->base}/service-blocks", ($this->block)(['service_id' => $this->resort->id]))
        ->assertCreated();
});

test('center-wide blocks and periods need every service type', function (): void {
    $periods = ['female' => ['days' => [1], 'hours' => ['09:00', '12:00']]];

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->base}/service-blocks", ($this->block)())
        ->assertForbidden();
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->base}/periods", $periods)
        ->assertForbidden();

    $this->user->syncCenterPermissions($this->center, CenterUserPermission::manageServicesPermissions());

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->base}/service-blocks", ($this->block)())
        ->assertCreated();
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->base}/periods", $periods)
        ->assertOk();
});
