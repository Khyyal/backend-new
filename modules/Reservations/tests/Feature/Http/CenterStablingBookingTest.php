<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\HorseFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Database\Factories\StablingFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);

    $this->service = BookingFixtures::service($this->center, StablingFactory::new()->create(), ServiceType::Stabling);
    $this->month = $this->service->priceOptions()->create(['price' => 150000, 'quantity' => 1, 'unit' => PriceOptionUnit::MONTH]);
    $this->threeMonths = $this->service->priceOptions()->create(['price' => 420000, 'quantity' => 3, 'unit' => PriceOptionUnit::MONTH]);
    $this->daily = $this->service->priceOptions()->create(['price' => 6000, 'quantity' => 1, 'unit' => PriceOptionUnit::DAY]);
    $this->grooming = $this->service->priceOptions()->create(['name' => 'Grooming', 'price' => 30000, 'unit' => PriceOptionUnit::OPTION]);

    $this->owner = ClientFactory::new()->create();
    $this->horse = HorseFactory::new()->for($this->center)->create(['client_id' => $this->owner->id]);
    $this->url = "/api/v1/centers/{$this->center->id}/reservations/stabling";
    $this->book = fn (array $body = []) => $this->actingAs($this->user, 'center_user')
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson($this->url, [
            'horse_id' => $this->horse->id,
            'service_id' => $this->service->id,
            'price_option_id' => $this->month->id,
            'start_date' => '2026-12-01',
            'is_paid' => true,
            ...$body,
        ]);
});

test('books a paid monthly stay with extras for the horse owner', function (): void {
    ($this->book)(['addon_ids' => [$this->grooming->id]])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.source', 'center')
        ->assertJsonPath('data.horse_id', $this->horse->id)
        ->assertJsonPath('data.client.id', $this->owner->id)
        ->assertJsonPath('data.date', '2026-12-01')
        ->assertJsonPath('data.end_date', '2026-12-31')
        ->assertJsonPath('data.price_option_id', $this->month->id)
        ->assertJsonPath('data.subtotal', 180000)
        ->assertJsonPath('data.total_amount', 180000)
        ->assertJsonPath('checkout.purchase.status', 'confirmed')
        ->assertJsonPath('checkout.payment.status', 'succeeded')
        ->assertJsonPath('checkout.payment.method', 'manual');

    expect(Reservation::sole()->lines)->toHaveCount(1);
});

test('a multi-month option ends on the last day of its months', function (): void {
    ($this->book)(['price_option_id' => $this->threeMonths->id, 'start_date' => '2027-01-31'])
        ->assertCreated()
        ->assertJsonPath('data.end_date', '2027-04-29');
});

test('an unpaid stay is confirmed with the payment pending', function (): void {
    ($this->book)(['is_paid' => false])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('checkout.purchase.status', 'pending')
        ->assertJsonPath('checkout.payment.status', 'pending')
        ->assertJsonPath('checkout.payment.method', 'cash_on_arrival');
});

test('the daily option opens a stay without end that keeps the horse busy until cancelled', function (): void {
    $id = ($this->book)(['price_option_id' => $this->daily->id])
        ->assertCreated()
        ->assertJsonPath('data.end_date', null)
        ->assertJsonPath('data.total_amount', 6000)
        ->json('data.id');

    ($this->book)(['start_date' => '2027-06-01'])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'horse_unavailable');

    Reservation::query()->whereKey($id)->update(['status' => 'cancelled']);

    ($this->book)(['start_date' => '2027-06-01'])->assertCreated();
});

test('overlapping stays of one horse are refused, back-to-back stays are not', function (): void {
    ($this->book)()->assertCreated();

    ($this->book)(['start_date' => '2026-12-31'])->assertUnprocessable()->assertJsonPath('reason', 'horse_unavailable');
    ($this->book)(['start_date' => '2026-11-25', 'price_option_id' => $this->daily->id])->assertUnprocessable()->assertJsonPath('reason', 'horse_unavailable');
    ($this->book)(['start_date' => '2027-01-01'])->assertCreated();

    $other = HorseFactory::new()->for($this->center)->forClient()->create();
    ($this->book)(['horse_id' => $other->id])->assertCreated();
});

test('a center total is charged as is, without offers', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(2000)->create();

    $reservation = ($this->book)(['total_amount' => 100000, 'is_paid' => false])
        ->assertCreated()
        ->assertJsonPath('data.subtotal', 150000)
        ->assertJsonPath('data.total_amount', 100000)
        ->assertJsonPath('checkout.purchase.discount_amount', 0)
        ->assertJsonPath('checkout.purchase.total_amount', 100000)
        ->json('data.id');

    expect(Reservation::find($reservation)->metadata['price_override']['calculated_total'])->toBe(150000);
});

test('a zero total confirms without a purchase', function (): void {
    ($this->book)(['total_amount' => 0])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('checkout', null);
});

test('refuses invalid stays', function (array $body, string $reason): void {
    if (($body['price_option_id'] ?? null) === 'grooming') {
        $body['price_option_id'] = $this->grooming->id;
    }

    if (($body['addon_ids'] ?? null) === ['threeMonths']) {
        $body['addon_ids'] = [$this->threeMonths->id];
    }

    ($this->book)($body)->assertUnprocessable()->assertJsonPath('reason', $reason);
})->with([
    'past date' => [['start_date' => '2026-11-19'], 'date_in_past'],
    'extra as duration' => [['price_option_id' => 'grooming'], 'invalid_price_option'],
    'duration as extra' => [['addon_ids' => ['threeMonths']], 'invalid_price_option'],
]);

test('refuses a center-owned horse, another center\'s horse and a non-stabling service', function (): void {
    $centerHorse = HorseFactory::new()->for($this->center)->create();
    $foreignHorse = HorseFactory::new()->forClient()->create();
    $riding = BookingFixtures::riding($this->center);

    ($this->book)(['horse_id' => $centerHorse->id])->assertUnprocessable()->assertJsonPath('reason', 'horse_not_client_owned');
    ($this->book)(['horse_id' => $foreignHorse->id])->assertUnprocessable()->assertJsonValidationErrors(['horse_id']);
    ($this->book)(['service_id' => $riding->id])->assertNotFound();
    expect(Reservation::count())->toBe(0);
});

test('needs reservation access', function (): void {
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageHorses]);

    ($this->book)()->assertForbidden();
});

test('the generic booking endpoint cannot book stabling without a horse', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/centers/{$this->center->id}/reservations", [
            'client_id' => $this->owner->id,
            'service_id' => $this->service->id,
            'date' => '2026-12-01',
            'price_option_id' => $this->month->id,
            'payment_method' => 'manual',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'horse_required');
});

test('ending an open-ended stay completes it today and frees the horse', function (): void {
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations, CenterUserPermission::EditReservations]);
    $id = ($this->book)(['price_option_id' => $this->daily->id])->assertCreated()->json('data.id');

    $this->travelTo('2026-12-10 09:00:00');

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/centers/{$this->center->id}/reservations/{$id}/end-stay")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.end_date', '2026-12-10')
        ->assertJsonPath('data.total_amount', 6000);

    ($this->book)(['start_date' => '2026-12-11'])->assertCreated();
});

test('a monthly stay can end early on a chosen day, keeping its planned end', function (): void {
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations, CenterUserPermission::EditReservations]);
    $id = ($this->book)()->assertCreated()->json('data.id');
    $url = "/api/v1/centers/{$this->center->id}/reservations/{$id}/end-stay";
    $end = fn (array $body = []) => $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $body);

    $end()->assertUnprocessable()->assertJsonPath('reason', 'not_started_yet');

    $this->travelTo('2026-12-20 09:00:00');

    $end(['end_date' => '2026-11-30'])->assertUnprocessable()->assertJsonPath('reason', 'invalid_stay_end');
    $end(['end_date' => '2026-12-21'])->assertUnprocessable()->assertJsonPath('reason', 'invalid_stay_end');
    $end(['end_date' => '2026-12-15'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.end_date', '2026-12-15');

    expect(Reservation::find($id)->metadata['planned_end_date'])->toBe('2026-12-31');
    $end()->assertUnprocessable()->assertJsonPath('reason', 'invalid_transition');
});

test('only stabling stays can be ended, with reservations.edit', function (): void {
    $id = ($this->book)()->assertCreated()->json('data.id');
    $this->travelTo('2026-12-05 09:00:00');
    $url = "/api/v1/centers/{$this->center->id}/reservations/{$id}/end-stay";

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url)->assertForbidden();

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations, CenterUserPermission::EditReservations]);
    $riding = Reservation::factory()->forService(BookingFixtures::riding($this->center))->create(['date' => '2026-12-01']);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/centers/{$this->center->id}/reservations/{$riding->id}/end-stay")
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'not_a_stabling_stay');
});
