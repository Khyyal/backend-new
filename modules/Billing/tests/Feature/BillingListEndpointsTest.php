<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Factories\FeatureFactory;
use Modules\Billing\Database\Factories\LimitFactory;
use Modules\Billing\Database\Factories\SubscriptionFactory;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('feature and limit lists return paginated data', function (): void {
    FeatureFactory::new()->create();
    LimitFactory::new()->create();
    $user = UserFactory::new()->create();

    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/admin/features')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/admin/limits')->assertOk()->assertJsonCount(1, 'data');
});

test('center subscription list returns paginated data', function (): void {
    $center = CenterFactory::new()->create();
    $user = UserFactory::new()->create();
    $center->users()->attach($user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);
    SubscriptionFactory::new()->forCenter($center)->active()->create();

    $this->actingAs($user, 'center_user')
        ->getJson('/api/center/subscriptions')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
