<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Events\SubscriptionCreated;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config(['purchase.default_gateway' => 'fake']);

    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);

    $this->checkout = fn (Plan $plan) => $this->actingAs($this->user, 'center_user')
        ->postJson('/api/center/subscriptions', ['plan_id' => $plan->id]);
});

function paidPlan(array $attributes = []): Plan
{
    return PlanFactory::new()->monthly()->active()->create(['price' => 19900, 'trial_days' => 0, ...$attributes]);
}

test('a paid plan opens a pending subscription with a platform purchase and online payment', function (): void {
    $plan = paidPlan();

    $response = ($this->checkout)($plan)->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('checkout.purchase.total_amount', 19900)
        ->assertJsonPath('checkout.payment.status', 'pending')
        ->assertJsonPath('checkout.payment.method', 'online')
        ->assertJsonPath('checkout.payment.payment_company', 'fake');

    expect($response->json('checkout.gateway.checkout_url'))->not->toBeNull();

    $purchase = Purchase::query()->with('items')->sole();
    expect($purchase->merchant_type)->toBe('platform')
        ->and($purchase->buyer_type)->toBe($this->center->getMorphClass())
        ->and($purchase->items->sole()->purchasable_type)->toBe('subscription')
        ->and($purchase->items->sole()->purchasable_id)->toBe($response->json('data.id'))
        ->and($purchase->fee()->exists())->toBeFalse();
});

test('free and trial plans start right away without a purchase', function (array $attributes, string $status): void {
    $plan = PlanFactory::new()->monthly()->active()->create($attributes);

    ($this->checkout)($plan)->assertCreated()
        ->assertJsonPath('data.status', $status)
        ->assertJsonPath('checkout', null);

    expect(Purchase::query()->exists())->toBeFalse();
})->with([
    'free' => [['price' => 0, 'trial_days' => 0], 'active'],
    'trial' => [['price' => 19900, 'trial_days' => 14], 'trialing'],
]);

test('a succeeded payment activates the subscription', function (): void {
    Event::fake([SubscriptionCreated::class]);
    $plan = paidPlan();

    $subscriptionId = ($this->checkout)($plan)->json('data.id');
    Event::assertNotDispatched(SubscriptionCreated::class);

    app(PaymentStateService::class)->markSucceeded(Payment::query()->sole());

    $subscription = Subscription::query()->findOrFail($subscriptionId);
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and(Purchase::query()->sole()->status)->toBe(PurchaseStatus::Confirmed)
        ->and($subscription->ends_at->toDateString())->toBe(now()->addMonthsNoOverflow(1)->toDateString())
        ->and($this->center->currentSubscription()?->id)->toBe($subscriptionId);
    Event::assertDispatched(SubscriptionCreated::class, 1);
});

test('a second checkout for the same plan is rejected while one is pending', function (): void {
    $plan = paidPlan();

    ($this->checkout)($plan)->assertCreated();
    ($this->checkout)($plan)->assertUnprocessable()->assertJsonValidationErrors('plan_id');
});

test('an inactive plan cannot be checked out', function (): void {
    ($this->checkout)(paidPlan(['status' => PlanStatus::InActive]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_id');
});

test('the release command voids stale unpaid checkouts and keeps recent ones', function (): void {
    $stalePlan = paidPlan();
    $recentPlan = paidPlan();

    $staleId = ($this->checkout)($stalePlan)->json('data.id');
    Purchase::query()->update(['created_at' => now()->subMinutes(31)]);
    $recentId = ($this->checkout)($recentPlan)->json('data.id');

    $this->artisan('billing:release-unpaid')->assertSuccessful();

    expect(Subscription::query()->find($staleId)->status)->toBe(SubscriptionStatus::Canceled)
        ->and(Subscription::query()->find($recentId)->status)->toBe(SubscriptionStatus::Pending);

    ($this->checkout)($stalePlan)->assertCreated();
});

test('a payment that succeeds after release is refunded and does not activate', function (): void {
    $plan = paidPlan();

    $subscriptionId = ($this->checkout)($plan)->json('data.id');
    Purchase::query()->update(['created_at' => now()->subMinutes(31)]);
    $this->artisan('billing:release-unpaid')->assertSuccessful();

    app(PaymentStateService::class)->markSucceeded(Payment::query()->sole());

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Refunded)
        ->and(Subscription::query()->find($subscriptionId)->status)->toBe(SubscriptionStatus::Canceled);
});
