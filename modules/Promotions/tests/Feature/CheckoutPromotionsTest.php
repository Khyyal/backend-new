<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\CouponFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Enums\RedemptionStatus;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Models\PromotionRedemption;
use Modules\Promotions\Services\ActivePromotionRepository;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Models\ServicePackage;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center);
    $this->option = $this->service->priceOptions()->first();
    $this->option->update(['price' => 10000]);

    $this->body = fn (array $overrides = []) => [
        'service_id' => $this->service->id,
        'date' => '2026-10-26',
        'start_time' => '10:00',
        'price_option_id' => $this->option->id,
        'payment_method' => 'online',
        ...$overrides,
    ];

    $this->book = fn (array $overrides = []) => $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)($overrides));

    $this->couponPromotion = fn (array $attributes = []) => PromotionFactory::new()->active()->coupon()->forCenter($this->center)->percentage(2000)->create($attributes);
});

test('the quote applies the running automatic offer without booking anything', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(1000)->create(['badge_label' => ['en' => '10% off']]);

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations/quote', ($this->body)())
        ->assertOk()
        ->assertJsonPath('data.subtotal_amount', 10000)
        ->assertJsonPath('data.discount_amount', 1000)
        ->assertJsonPath('data.total_amount', 9000)
        ->assertJsonPath('data.adjustments.0.label', '10% off')
        ->assertJsonPath('data.coupon', null);

    expect(Reservation::query()->count())->toBe(0);
});

test('the quote reports a coupon that cannot be applied instead of failing', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations/quote', ($this->body)(['coupon_code' => 'NOPE']))
        ->assertOk()
        ->assertJsonPath('data.total_amount', 10000)
        ->assertJsonPath('data.coupon.status', 'invalid')
        ->assertJsonPath('data.coupon.message', 'This code is not valid.');
});

test('drafts, ended offers and offers of other centers are not applied', function (): void {
    PromotionFactory::new()->forCenter($this->center)->create();
    PromotionFactory::new()->active()->forCenter($this->center)->create(['ends_at' => now()->subMinute()]);
    PromotionFactory::new()->active()->forCenter(CenterFactory::new()->create())->create();

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations/quote', ($this->body)())
        ->assertJsonPath('data.discount_amount', 0);
});

test('a weekly schedule limits when the offer applies, including past midnight', function (): void {
    $promotion = PromotionFactory::new()->active()->percentage(1000)->create();
    $promotion->schedules()->create(['day_of_week' => 1, 'start_time' => '22:00', 'end_time' => '02:00']);
    $quote = fn () => $this->actingAs($this->client, 'client')->postJson('/api/v1/clients/reservations/quote', ($this->body)())->json('data.discount_amount');

    $this->travelTo('2026-10-20 08:00:00'); // Tuesday morning
    expect($quote())->toBe(0);

    $this->travelTo('2026-10-20 01:30:00'); // Tuesday, still Monday night's window
    expect($quote())->toBe(1000);
});

test('booking with a coupon discounts the purchase and records its use', function (): void {
    $coupon = CouponFactory::new()->forPromotion(($this->couponPromotion)())->withCode('SUMMER20')->create();

    ($this->book)(['coupon_code' => ' summer20 '])
        ->assertCreated()
        ->assertJsonPath('checkout.purchase.subtotal', 10000)
        ->assertJsonPath('checkout.purchase.discount_amount', 2000)
        ->assertJsonPath('checkout.purchase.total_amount', 8000)
        ->assertJsonPath('checkout.purchase.coupon_code', 'SUMMER20')
        ->assertJsonPath('checkout.payment.amount', 8000);

    $purchase = Purchase::query()->sole();

    expect($purchase->items->sole()->discount_amount)->toBe(2000)
        ->and($purchase->adjustments->sole())->toMatchArray(['type' => 'coupon', 'code' => 'SUMMER20', 'amount' => 2000, 'funded_by' => 'center', 'center_share_amount' => 2000])
        ->and($coupon->fresh()->used_count)->toBe(1)
        ->and($coupon->promotion->fresh()->usage_count)->toBe(1)
        ->and(PromotionRedemption::query()->sole())->toMatchArray(['purchase_id' => $purchase->id, 'client_id' => $this->client->id, 'coupon_id' => $coupon->id, 'discount_amount' => 2000]);
});

test('a coupon that cannot be applied fails the booking with 422 and books nothing', function (): void {
    CouponFactory::new()->forPromotion(($this->couponPromotion)())->withCode('LATER')->create(['starts_at' => now()->addDay()]);

    ($this->book)(['coupon_code' => 'LATER'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'coupon_rejected')
        ->assertJsonPath('coupon.status', 'not_started')
        ->assertJsonValidationErrors(['coupon_code' => 'This code is not active yet.']);

    expect(Reservation::query()->count())->toBe(0)
        ->and(Purchase::query()->count())->toBe(0);
});

test('a coupon worse than the automatic offer is rejected as not better', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(5000)->create();
    CouponFactory::new()->forPromotion(($this->couponPromotion)())->withCode('SMALL')->create();

    ($this->book)(['coupon_code' => 'SMALL'])
        ->assertUnprocessable()
        ->assertJsonPath('coupon.status', 'coupon_not_better');
});

test('a total different from the expected one fails with 409 and the new quote', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(1000)->create();

    ($this->book)(['expected_total_amount' => 10000])
        ->assertStatus(409)
        ->assertJsonPath('code', 'price_changed')
        ->assertJsonPath('data.total_amount', 9000);

    expect(Reservation::query()->count())->toBe(0);
});

test('a used up coupon is rejected, and the last use cannot be taken twice', function (): void {
    $coupon = CouponFactory::new()->forPromotion(($this->couponPromotion)())->withCode('ONCE')->create(['usage_limit' => 1]);

    ($this->book)(['coupon_code' => 'ONCE'])->assertCreated();

    $this->actingAs(ClientFactory::new()->create(), 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)(['coupon_code' => 'ONCE', 'start_time' => '11:00']))
        ->assertUnprocessable()
        ->assertJsonPath('coupon.status', 'exhausted');

    expect($coupon->fresh()->used_count)->toBe(1);
});

test('the per-client limit stops a second use by the same client', function (): void {
    CouponFactory::new()->forPromotion(($this->couponPromotion)())->withCode('MINE')->create(['per_client_usage_limit' => 1]);

    ($this->book)(['coupon_code' => 'MINE'])->assertCreated();

    ($this->book)(['coupon_code' => 'MINE', 'start_time' => '11:00'])
        ->assertUnprocessable()
        ->assertJsonPath('coupon.status', 'client_limit_reached');
});

test('a first-booking offer applies only to the first purchase', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(1000)->create(['first_booking_only' => true]);

    ($this->book)()->assertJsonPath('checkout.purchase.discount_amount', 1000);
    ($this->book)(['start_time' => '11:00'])->assertJsonPath('checkout.purchase.discount_amount', 0);
});

test('a purchase discounted to nothing is confirmed without a payment', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(10000)->create();

    $response = ($this->book)()
        ->assertCreated()
        ->assertJsonPath('checkout.purchase.total_amount', 0)
        ->assertJsonPath('checkout.payment', null);

    expect(Payment::query()->count())->toBe(0)
        ->and(Reservation::query()->find($response->json('data.id'))->status->value)->toBe('confirmed');
});

test('cancelling the purchase gives the coupon use back', function (): void {
    $coupon = CouponFactory::new()->forPromotion(($this->couponPromotion)(['per_client_usage_limit' => 1]))->withCode('BACK')->create();

    ($this->book)(['coupon_code' => 'BACK'])->assertCreated();
    app(PurchaseStateService::class)->cancel(Purchase::query()->sole());

    expect($coupon->fresh()->used_count)->toBe(0)
        ->and($coupon->promotion->fresh()->usage_count)->toBe(0)
        ->and(PromotionRedemption::query()->sole()->status)->toBe(RedemptionStatus::Released);

    ($this->book)(['coupon_code' => 'BACK', 'start_time' => '11:00'])->assertCreated();
});

test('a package purchase gets the coupon discount', function (): void {
    CouponFactory::new()->forPromotion(($this->couponPromotion)())->withCode('PACK')->create();
    $servicePackage = ServicePackage::query()->create([
        'service_id' => $this->service->id,
        'price_option_id' => $this->option->id,
        'count' => 4,
        'price' => 35000,
        'duration' => 2,
    ]);

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/packages/quote', ['service_package_id' => $servicePackage->id, 'coupon_code' => 'PACK'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 28000)
        ->assertJsonPath('data.coupon.status', 'applied');

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/packages', ['service_package_id' => $servicePackage->id, 'coupon_code' => 'PACK'])
        ->assertCreated()
        ->assertJsonPath('checkout.purchase.total_amount', 28000);

    expect(ClientPackage::query()->sole()->price)->toBe(35000);
});

test('center staff can apply a coupon to a walk-in for the client', function (): void {
    CouponFactory::new()->forPromotion(($this->couponPromotion)())->withCode('DESK')->create();
    $staff = UserFactory::new()->create();
    $this->center->users()->attach($staff->id, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString(), 'is_primary' => false]);
    $staff->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);

    $this->actingAs($staff, 'center_user')
        ->postJson("/api/v1/centers/{$this->center->id}/reservations", ($this->body)(['client_id' => $this->client->id, 'payment_method' => 'manual', 'coupon_code' => 'DESK']))
        ->assertCreated()
        ->assertJsonPath('checkout.purchase.total_amount', 8000)
        ->assertJsonPath('checkout.purchase.status', 'confirmed');

    expect(PromotionRedemption::query()->sole()->client_id)->toBe($this->client->id);
});

test('an offer paused while checking out fails the booking with 409', function (): void {
    $promotion = PromotionFactory::new()->active()->forCenter($this->center)->percentage(1000)->create();

    // The cached shortlist still holds the active promotion; only the commit re-checks it.
    app(ActivePromotionRepository::class)->liveFor($this->center->id, now());
    Promotion::withoutEvents(fn () => $promotion->update(['status' => 'paused']));

    ($this->book)()
        ->assertStatus(409)
        ->assertJsonPath('code', 'price_changed');

    expect(Reservation::query()->count())->toBe(0);
});

test('coupon codes are throttled per client', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->actingAs($this->client, 'client')->postJson('/api/v1/clients/reservations/quote', ($this->body)(['coupon_code' => "TRY{$attempt}"]))->assertOk();
    }

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations/quote', ($this->body)(['coupon_code' => 'TRY11']))
        ->assertTooManyRequests();
});
