<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Enums\PlanStatus;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('center user lists only active plans and shows one', function (): void {
    $center = CenterFactory::new()->create();
    $user = UserFactory::new()->create();
    $center->users()->attach($user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);

    $active = PlanFactory::new()->monthly()->active()->create();
    PlanFactory::new()->monthly()->create(['status' => PlanStatus::InActive]);

    $this->actingAs($user, 'center_user');

    $this->getJson('/api/center/plans')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);

    $this->getJson("/api/center/plans/{$active->id}")->assertOk();
});

test('guests cannot list plans', function (): void {
    $this->getJson('/api/center/plans')->assertUnauthorized();
});
