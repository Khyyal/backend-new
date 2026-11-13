<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\HorseFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\StayInvoice;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Database\Factories\HorseCareFactory;
use Modules\Services\Database\Factories\HorseServiceFactory;
use Modules\Services\Database\Factories\StablingFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-12-05 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations, CenterUserPermission::EditReservations]);

    $this->stabling = BookingFixtures::service($this->center, StablingFactory::new()->create(), ServiceType::Stabling);
    $this->horse = HorseFactory::new()->for($this->center)->forClient()->create();
    $this->stay = ReservationFactory::new()->forService($this->stabling)->create([
        'client_id' => $this->horse->client_id,
        'horse_id' => $this->horse->id,
        'status' => ReservationStatus::Confirmed,
        'date' => '2026-12-01',
        'end_date' => '2026-12-31',
    ]);

    $this->shoeing = BookingFixtures::service($this->center, HorseServiceFactory::new()->create(), ServiceType::HorseService);
    $this->fullSet = $this->shoeing->priceOptions()->create(['name' => 'Full set', 'price' => 25000, 'unit' => PriceOptionUnit::OPTION]);
    $this->care = BookingFixtures::service($this->center, HorseCareFactory::new()->create(), ServiceType::HorseCare);
    $this->wash = $this->care->priceOptions()->create(['name' => 'Wash', 'price' => 8000, 'unit' => PriceOptionUnit::OPTION]);

    $this->url = fn (?int $id = null) => "/api/v1/centers/{$this->center->id}/reservations/".($id ?? $this->stay->id).'/invoices';
    $this->invoice = fn (array $body, ?int $id = null) => $this->actingAs($this->user, 'center_user')
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(($this->url)($id), ['date' => '2026-12-10', 'is_paid' => true, ...$body]);
});

test('bills a paid horse service at its option price', function (): void {
    ($this->invoice)(['kind' => 'horse_service', 'service_id' => $this->shoeing->id, 'price_option_id' => $this->fullSet->id])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'horse_service')
        ->assertJsonPath('data.price', 25000)
        ->assertJsonPath('data.total_amount', 25000)
        ->assertJsonPath('data.service.id', $this->shoeing->id)
        ->assertJsonPath('data.price_option.id', $this->fullSet->id)
        ->assertJsonPath('data.purchase.status', 'confirmed')
        ->assertJsonPath('data.payment_status', 'succeeded')
        ->assertJsonPath('checkout.payment.method', 'manual');

    expect(StayInvoice::sole())
        ->client_id->toBe($this->horse->client_id)
        ->service_id->toBe($this->shoeing->id);
});

test('bills an unpaid horse care with the payment pending', function (): void {
    ($this->invoice)(['kind' => 'horse_care', 'service_id' => $this->care->id, 'price_option_id' => $this->wash->id, 'is_paid' => false])
        ->assertCreated()
        ->assertJsonPath('data.total_amount', 8000)
        ->assertJsonPath('data.purchase.status', 'pending')
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('checkout.payment.method', 'cash_on_arrival');
});

test('bills a standalone line and custom totals, without offers', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(2000)->create();

    ($this->invoice)(['kind' => 'standalone', 'name' => 'Vet visit', 'description' => 'Hoof check', 'total_amount' => 30000])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Vet visit')
        ->assertJsonPath('data.description', 'Hoof check')
        ->assertJsonPath('data.service', null)
        ->assertJsonPath('data.total_amount', 30000)
        ->assertJsonPath('checkout.purchase.discount_amount', 0)
        ->assertJsonPath('checkout.purchase.total_amount', 30000);

    $id = ($this->invoice)(['kind' => 'horse_service', 'service_id' => $this->shoeing->id, 'price_option_id' => $this->fullSet->id, 'total_amount' => 20000])
        ->assertCreated()
        ->assertJsonPath('data.price', 25000)
        ->assertJsonPath('data.total_amount', 20000)
        ->assertJsonPath('checkout.purchase.total_amount', 20000)
        ->json('data.id');

    expect(StayInvoice::find($id)->metadata['price_override']['calculated_total'])->toBe(25000);
});

test('a zero total records the invoice without a purchase', function (): void {
    ($this->invoice)(['kind' => 'standalone', 'name' => 'Free check', 'total_amount' => 0])
        ->assertCreated()
        ->assertJsonPath('data.purchase', null)
        ->assertJsonPath('checkout', null);
});

test('refuses dates outside the stay and inactive stays', function (): void {
    $body = ['kind' => 'standalone', 'name' => 'Extra', 'total_amount' => 100];

    ($this->invoice)([...$body, 'date' => '2026-11-30'])->assertUnprocessable()->assertJsonPath('reason', 'invoice_date_outside_stay');
    ($this->invoice)([...$body, 'date' => '2027-01-01'])->assertUnprocessable()->assertJsonPath('reason', 'invoice_date_outside_stay');

    $this->stay->update(['end_date' => null]);
    ($this->invoice)([...$body, 'date' => '2027-03-01'])->assertCreated();

    $this->stay->update(['status' => ReservationStatus::Completed]);
    ($this->invoice)($body)->assertUnprocessable()->assertJsonPath('reason', 'invoice_stay_not_active');

    $riding = ReservationFactory::new()->forService(BookingFixtures::riding($this->center))->create(['date' => '2026-12-01']);
    ($this->invoice)($body, $riding->id)->assertUnprocessable()->assertJsonPath('reason', 'not_a_stabling_stay');
});

test('refuses a service of the wrong type or center and a foreign option', function (): void {
    $foreign = BookingFixtures::service(CenterFactory::new()->create(), HorseServiceFactory::new()->create(), ServiceType::HorseService);

    ($this->invoice)(['kind' => 'horse_care', 'service_id' => $this->shoeing->id, 'price_option_id' => $this->fullSet->id])
        ->assertUnprocessable()->assertJsonPath('reason', 'invalid_invoice_service');
    ($this->invoice)(['kind' => 'horse_service', 'service_id' => $foreign->id, 'price_option_id' => $this->fullSet->id])
        ->assertUnprocessable()->assertJsonPath('reason', 'invalid_invoice_service');
    ($this->invoice)(['kind' => 'horse_service', 'service_id' => $this->shoeing->id, 'price_option_id' => $this->wash->id])
        ->assertUnprocessable()->assertJsonPath('reason', 'invalid_price_option');
    ($this->invoice)(['kind' => 'standalone'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'total_amount']);
    ($this->invoice)(['kind' => 'horse_service'])
        ->assertUnprocessable()->assertJsonValidationErrors(['service_id', 'price_option_id']);

    expect(StayInvoice::count())->toBe(0);
});

test('adding needs reservations.edit; listing shows the invoices with their purchase', function (): void {
    ($this->invoice)(['kind' => 'standalone', 'name' => 'First', 'total_amount' => 100])->assertCreated();
    ($this->invoice)(['kind' => 'horse_care', 'service_id' => $this->care->id, 'price_option_id' => $this->wash->id, 'is_paid' => false])->assertCreated();

    $this->getJson(($this->url)())
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.kind', 'horse_care')
        ->assertJsonPath('data.0.purchase.status', 'pending')
        ->assertJsonPath('data.1.name', 'First');

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
    ($this->invoice)(['kind' => 'standalone', 'name' => 'x', 'total_amount' => 1])->assertForbidden();
});

test('invoice purchases follow purchase visibility and show their details', function (): void {
    $purchaseId = ($this->invoice)(['kind' => 'horse_service', 'service_id' => $this->shoeing->id, 'price_option_id' => $this->fullSet->id])
        ->json('checkout.purchase.id');
    $viewer = UserFactory::new()->create();
    $this->center->users()->attach($viewer->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $url = "/api/v1/centers/{$this->center->id}/purchases";

    $viewer->syncCenterPermissions($this->center, [CenterUserPermission::ViewHorseServicePurchases]);
    $this->actingAs($viewer, 'center_user')->getJson($url)->assertOk()->assertJsonCount(0, 'data');

    $viewer->syncCenterPermissions($this->center, [CenterUserPermission::ViewHorseServicePurchases, CenterUserPermission::ViewUnassignedPurchases]);
    $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');

    $this->getJson("{$url}/{$purchaseId}")
        ->assertOk()
        ->assertJsonPath('data.items.0.purchasable_type', 'stay_invoice')
        ->assertJsonPath('data.items.0.details.kind', 'horse_service')
        ->assertJsonPath('data.items.0.details.reservation_id', $this->stay->id);
});

test('cancelling a paid invoice refunds it and reverses the fee, leaving the stay alone', function (): void {
    $created = ($this->invoice)(['kind' => 'horse_service', 'service_id' => $this->shoeing->id, 'price_option_id' => $this->fullSet->id])->json();
    $purchase = Purchase::find($created['checkout']['purchase']['id']);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(($this->url)()."/{$created['data']['id']}/cancel", ['reason' => 'Billed by mistake'])
        ->assertOk()
        ->assertJsonPath('data.purchase.status', 'cancelled')
        ->assertJsonPath('data.payment_status', 'refunded')
        ->assertJsonPath('data.refunded_amount', 25000)
        ->assertJsonPath('data.cancellation_reason', 'Billed by mistake')
        ->assertJsonPath('data.cancelled_at', fn (?string $at) => $at !== null);

    expect($purchase->fee()->first()?->reversed_at)->not->toBeNull()
        ->and($this->stay->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and(StayInvoice::find($created['data']['id'])->cancelledBy->is($this->user))->toBeTrue();
});

test('cancelling an unpaid invoice cancels its payment and purchase without a refund', function (): void {
    $id = ($this->invoice)(['kind' => 'horse_care', 'service_id' => $this->care->id, 'price_option_id' => $this->wash->id, 'is_paid' => false])->json('data.id');
    $board = "/api/v1/centers/{$this->center->id}/horse-stays?type=active";
    $this->getJson($board)->assertJsonPath('data.0.unpaid_operations_count', 1);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(($this->url)()."/{$id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.purchase.status', 'cancelled')
        ->assertJsonPath('data.payment_status', 'cancelled')
        ->assertJsonPath('data.refunded_amount', 0);

    $this->getJson($board)->assertJsonPath('data.0.unpaid_operations_count', 0);
});

test('a free invoice is only marked cancelled, and only once', function (): void {
    $id = ($this->invoice)(['kind' => 'standalone', 'name' => 'Free check', 'total_amount' => 0])->json('data.id');
    $cancel = fn () => $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson(($this->url)()."/{$id}/cancel");

    $cancel()->assertOk()->assertJsonPath('data.purchase', null)->assertJsonPath('data.cancelled_at', fn (?string $at) => $at !== null);
    $cancel()->assertUnprocessable()->assertJsonPath('reason', 'invoice_already_cancelled');
});

test('cancelling is scoped to the stay and needs reservations.edit', function (): void {
    $id = ($this->invoice)(['kind' => 'standalone', 'name' => 'Extra', 'total_amount' => 100])->json('data.id');
    $otherStay = ReservationFactory::new()->forService($this->stabling)->create(['status' => ReservationStatus::Confirmed]);
    $foreign = ReservationFactory::new()->create();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson(($this->url)($otherStay->id)."/{$id}/cancel")->assertNotFound();
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson(($this->url)($foreign->id)."/{$id}/cancel")->assertNotFound();

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson(($this->url)()."/{$id}/cancel")->assertForbidden();
    expect(StayInvoice::find($id)->isCancelled())->toBeFalse();
});

test('refunding the invoice purchase from the purchases screen cancels the invoice', function (): void {
    $this->user->syncCenterPermissions($this->center, [
        CenterUserPermission::ManageReservations,
        CenterUserPermission::EditReservations,
        CenterUserPermission::ViewHorseServicePurchases,
        CenterUserPermission::ViewUnassignedPurchases,
    ]);
    $created = ($this->invoice)(['kind' => 'horse_service', 'service_id' => $this->shoeing->id, 'price_option_id' => $this->fullSet->id])->json();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/centers/{$this->center->id}/purchases/{$created['checkout']['purchase']['id']}/refund")
        ->assertOk();

    expect(StayInvoice::find($created['data']['id'])->isCancelled())->toBeTrue();
});
