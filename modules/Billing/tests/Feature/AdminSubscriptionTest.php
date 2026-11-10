<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Database\Factories\SubscriptionFactory;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Events\SubscriptionCanceled;
use Modules\Billing\Events\SubscriptionCreated;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config(['purchase.default_gateway' => 'fake']);
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->center = CenterFactory::new()->create();
});

function adminPlan(array $attributes = []): Plan
{
    return PlanFactory::new()->monthly()->active()->create(['price' => 19900, 'trial_days' => 0, ...$attributes]);
}

test('guests are unauthenticated and users without permission are forbidden', function (): void {
    $plan = adminPlan();
    $url = "/api/v1/admin/centers/{$this->center->id}/subscription";

    $this->getJson('/api/v1/admin/subscriptions')->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson('/api/v1/admin/subscriptions')->assertForbidden();
    $this->postJson($url, ['plan_id' => $plan->id, 'payment_mode' => 'free'])->assertForbidden();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnySubscriptions])->create(), 'system_user');
    $this->getJson('/api/v1/admin/subscriptions')->assertOk();
    $this->postJson($url, ['plan_id' => $plan->id, 'payment_mode' => 'free'])->assertForbidden();
});

test('admin lists subscriptions with filters and the center', function (): void {
    $other = CenterFactory::new()->create();
    $plan = adminPlan();
    $match = SubscriptionFactory::new()->forCenter($this->center)->active()->create(['plan_id' => $plan->id]);
    SubscriptionFactory::new()->forCenter($other)->expired()->create();

    $this->actingAs($this->admin, 'system_user');

    $this->getJson('/api/v1/admin/subscriptions')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/admin/subscriptions?center_id={$this->center->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.center.id', $this->center->id);
    $this->getJson('/api/v1/admin/subscriptions?status=expired')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/admin/subscriptions?plan_id={$plan->id}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/admin/subscriptions/{$match->id}")->assertOk()->assertJsonPath('data.id', $match->id);
});

test('admin sees the current subscription of a center', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $url = "/api/v1/admin/centers/{$this->center->id}/subscription";

    $this->getJson($url)->assertOk()->assertJsonPath('data', null);

    $current = SubscriptionFactory::new()->forCenter($this->center)->active()->create();
    $this->getJson($url)->assertOk()->assertJsonPath('data.id', $current->id);
});

test('free change replaces the current subscription without payment and is audited', function (): void {
    $old = SubscriptionFactory::new()->forCenter($this->center)->active()->create();
    $plan = adminPlan(['trial_days' => 14]);
    Event::fake([SubscriptionCreated::class, SubscriptionCanceled::class]);

    $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/subscription", ['plan_id' => $plan->id, 'payment_mode' => 'free'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.plan_id', $plan->id)
        ->assertJsonPath('checkout', null);

    expect($old->fresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($this->center->currentSubscription()->plan_id)->toBe($plan->id);
    Event::assertDispatched(SubscriptionCanceled::class);
    Event::assertDispatched(SubscriptionCreated::class);

    $log = Activity::query()->where('log_name', 'subscription')->where('subject_id', $old->id)->latest('id')->first();
    expect($log->causer_id)->toBe($this->admin->id);
});

test('free change works when the center has no subscription', function (): void {
    $plan = adminPlan();

    $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/subscription", ['plan_id' => $plan->id, 'payment_mode' => 'free'])
        ->assertCreated();

    expect($this->center->currentSubscription()->plan_id)->toBe($plan->id);
});

test('required change opens a pending subscription and replaces the old one once paid', function (): void {
    $old = SubscriptionFactory::new()->forCenter($this->center)->active()->create();
    $plan = adminPlan();

    $response = $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/subscription", ['plan_id' => $plan->id, 'payment_mode' => 'required'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('checkout.purchase.total_amount', 19900);

    expect($old->fresh()->status)->toBe(SubscriptionStatus::Active);

    app(PaymentStateService::class)->markSucceeded(Payment::query()->sole());

    expect($old->fresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and(Subscription::query()->find($response->json('data.id'))->status)->toBe(SubscriptionStatus::Active)
        ->and($this->center->currentSubscription()->id)->toBe($response->json('data.id'));
});

test('invalid changes are rejected', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $url = "/api/v1/admin/centers/{$this->center->id}/subscription";
    $inactive = PlanFactory::new()->monthly()->inactive()->create();
    $free = adminPlan(['price' => 0]);
    $trial = adminPlan(['trial_days' => 7]);
    $current = adminPlan();
    SubscriptionFactory::new()->forCenter($this->center)->active()->create(['plan_id' => $current->id]);

    $this->postJson($url, ['plan_id' => $inactive->id, 'payment_mode' => 'free'])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    $this->postJson($url, ['plan_id' => $free->id, 'payment_mode' => 'required'])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    $this->postJson($url, ['plan_id' => $trial->id, 'payment_mode' => 'required'])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    $this->postJson($url, ['plan_id' => $current->id, 'payment_mode' => 'required'])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    $this->postJson($url, ['plan_id' => $current->id, 'payment_mode' => 'bogus'])->assertUnprocessable()->assertJsonValidationErrors('payment_mode');
});

test('admin sorts subscriptions by start or end date and rejects unknown sorts', function (): void {
    $endsLate = SubscriptionFactory::new()->forCenter($this->center)->active()->create(['starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-12-01 00:00:00']);
    $endsSoon = SubscriptionFactory::new()->forCenter(CenterFactory::new()->create())->active()->create(['starts_at' => '2026-02-01 00:00:00', 'ends_at' => '2026-03-01 00:00:00']);

    $this->actingAs($this->admin, 'system_user');
    $ids = fn (string $sort): array => collect($this->getJson("/api/v1/admin/subscriptions?sort={$sort}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('ends_at'))->toBe([$endsSoon->id, $endsLate->id])
        ->and($ids('-ends_at'))->toBe([$endsLate->id, $endsSoon->id])
        ->and($ids('starts_at'))->toBe([$endsLate->id, $endsSoon->id]);

    $this->getJson('/api/v1/admin/subscriptions?sort=status')->assertUnprocessable()->assertJsonValidationErrors('sort');
});
