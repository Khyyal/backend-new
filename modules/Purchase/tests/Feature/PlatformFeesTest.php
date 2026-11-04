<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Enums\FundedBy;
use Modules\Purchase\Database\Factories\FeeRuleFactory;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Models\FeeRule;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\FeeRuleResolver;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create(['custom_site_enabled' => true]);
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center);
    $this->option = $this->service->priceOptions()->first();
    $this->option->update(['price' => 10000]);

    $this->body = [
        'service_id' => $this->service->id,
        'date' => '2026-10-26',
        'start_time' => '10:00',
        'price_option_id' => $this->option->id,
        'payment_method' => 'online',
    ];

    $this->book = fn (?string $channel = null) => $this->actingAs($this->client, 'client')
        ->withHeaders($channel === null ? [] : ['X-Channel' => $channel])
        ->postJson('/api/v1/clients/reservations', $this->body)
        ->assertCreated();
});

test('the most specific active rule applies', function (): void {
    $resolve = fn (?PurchaseChannel $channel): ?FeeRule => app(FeeRuleResolver::class)->resolve($this->center, $channel);
    $other = CenterFactory::new()->create();

    expect($resolve(PurchaseChannel::KhyyalApp))->toBeNull();

    $platformFallback = FeeRuleFactory::new()->create();
    FeeRuleFactory::new()->forCenter($other)->forChannel(PurchaseChannel::KhyyalApp)->create();
    expect($resolve(PurchaseChannel::KhyyalApp)->is($platformFallback))->toBeTrue();

    $centerFallback = FeeRuleFactory::new()->forCenter($this->center)->create();
    expect($resolve(PurchaseChannel::KhyyalApp)->is($centerFallback))->toBeTrue();

    $platformChannel = FeeRuleFactory::new()->forChannel(PurchaseChannel::KhyyalApp)->create();
    expect($resolve(PurchaseChannel::KhyyalApp)->is($platformChannel))->toBeTrue()
        ->and($resolve(null)->is($centerFallback))->toBeTrue();

    $centerChannel = FeeRuleFactory::new()->forCenter($this->center)->forChannel(PurchaseChannel::KhyyalApp)->create();
    expect($resolve(PurchaseChannel::KhyyalApp)->is($centerChannel))->toBeTrue();

    $centerChannel->update(['is_active' => false]);
    expect($resolve(PurchaseChannel::KhyyalApp)->is($platformChannel))->toBeTrue();
});

test('the fee adds the percentage and fixed amount within the limits, never above the base', function (array $rule, int $base, int $fee): void {
    expect(FeeRuleFactory::new()->make($rule)->calculate($base))->toBe($fee);
})->with([
    'percentage and fixed' => [['percentage_bps' => 1000, 'fixed_amount' => 50], 10000, 1050],
    'raised to the minimum' => [['percentage_bps' => 100, 'min_amount' => 500], 10000, 500],
    'lowered to the maximum' => [['percentage_bps' => 5000, 'max_amount' => 2000], 10000, 2000],
    'capped at the base' => [['percentage_bps' => 0, 'fixed_amount' => 900], 500, 500],
    'nothing earned' => [['percentage_bps' => 1000, 'fixed_amount' => 50], 0, 0],
]);

test('a client purchase records its channel and the fee for it', function (): void {
    FeeRuleFactory::new()->create(['percentage_bps' => 500]);
    FeeRuleFactory::new()->forChannel(PurchaseChannel::KhyyalApp)->create(['percentage_bps' => 1000, 'fixed_amount' => 100]);

    ($this->book)('khyyal_app');

    $purchase = Purchase::query()->with('fee')->sole();
    expect($purchase->channel)->toBe(PurchaseChannel::KhyyalApp)
        ->and($purchase->fee->base_amount)->toBe(10000)
        ->and($purchase->fee->percentage_bps)->toBe(1000)
        ->and($purchase->fee->amount)->toBe(1100);
});

test('an unknown or center-only channel falls back', function (?string $header): void {
    FeeRuleFactory::new()->create(['percentage_bps' => 500]);

    ($this->book)($header);

    $purchase = Purchase::query()->with('fee')->sole();
    expect($purchase->channel)->toBeNull()
        ->and($purchase->fee->amount)->toBe(500);
})->with([
    'missing' => [null],
    'unknown' => ['tv_app'],
    'dashboard' => ['center_dashboard'],
]);

test('a center app or website only counts when the center has one', function (): void {
    FeeRuleFactory::new()->forChannel(PurchaseChannel::CenterWeb)->create(['percentage_bps' => 200]);

    ($this->book)('center_web');
    expect(Purchase::query()->latest('id')->first()->channel)->toBe(PurchaseChannel::CenterWeb);

    $this->center->update(['custom_site_enabled' => false]);
    $this->body['start_time'] = '11:00';
    ($this->book)('center_web');
    expect(Purchase::query()->latest('id')->first()->channel)->toBeNull();
});

test('platform-funded discounts keep the fee base while center-funded ones lower it', function (FundedBy $fundedBy, int $base): void {
    FeeRuleFactory::new()->create(['percentage_bps' => 1000]);
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(2000)->create(['funded_by' => $fundedBy->value]);

    ($this->book)('khyyal_app');

    $purchase = Purchase::query()->with('fee')->sole();
    expect($purchase->total_amount)->toBe(8000)
        ->and($purchase->fee->base_amount)->toBe($base)
        ->and($purchase->fee->amount)->toBe(intdiv($base, 10));
})->with([
    'platform' => [FundedBy::Platform, 10000],
    'center' => [FundedBy::Center, 8000],
]);

test('a walk-in booked by center staff is a dashboard purchase', function (): void {
    FeeRuleFactory::new()->forChannel(PurchaseChannel::CenterDashboard)->create(['percentage_bps' => 0, 'fixed_amount' => 300]);
    $user = UserFactory::new()->create();
    $this->center->users()->attach($user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);

    $this->actingAs($user, 'center_user')
        ->postJson("/api/v1/centers/{$this->center->id}/reservations", [...$this->body, 'client_id' => $this->client->id, 'payment_method' => 'manual'])
        ->assertCreated();

    $purchase = Purchase::query()->with('fee')->sole();
    expect($purchase->channel)->toBe(PurchaseChannel::CenterDashboard)
        ->and($purchase->fee->amount)->toBe(300);
});

test('the fee is reversed when the purchase is cancelled', function (): void {
    FeeRuleFactory::new()->create(['percentage_bps' => 1000]);
    ($this->book)('khyyal_app');

    app(PurchaseStateService::class)->cancel(Purchase::query()->sole());

    $fee = Purchase::query()->with('fee')->sole()->fee;
    expect($fee->reversed_at)->not->toBeNull()
        ->and($fee->owedAmount())->toBe(0);
});

test('the fee is reversed on a full refund only', function (): void {
    FeeRuleFactory::new()->create(['percentage_bps' => 1000]);
    ($this->book)('khyyal_app');
    $payments = app(PaymentStateService::class);
    $payment = $payments->markSucceeded(Purchase::query()->sole()->latestPayment);

    $payment = $payments->recordRefund($payment, 4000);
    expect(Purchase::query()->with('fee')->sole()->fee->reversed_at)->toBeNull();

    $payments->recordRefund($payment, 6000);
    expect(Purchase::query()->with('fee')->sole()->fee->reversed_at)->not->toBeNull();
});
