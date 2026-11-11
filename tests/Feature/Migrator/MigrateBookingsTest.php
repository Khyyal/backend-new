<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Database\Factories\EventFactory;
use Modules\Services\Database\Factories\MembershipFactory;
use Modules\Services\Database\Factories\PriceOptionFactory;
use Modules\Services\Database\Factories\ResortFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Database\Factories\ServicePackageFactory;
use Modules\Services\Enums\ServiceType;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

const BOOKING_MIGRATORS = ['migrator:client-memberships', 'migrator:client-packages', 'migrator:reservations', 'migrator:event-reservations', 'migrator:resort-reservations', 'migrator:purchases'];

/**
 * Runs the booking migrators in their `migrator:run` order.
 *
 * @param  array<string, mixed>  $options
 */
function runBookingMigrators(object $test, array $options = []): void
{
    foreach (BOOKING_MIGRATORS as $migrator) {
        $test->artisan($migrator, $options)->assertSuccessful();
    }
}

function mappedReservation(string $entity, int $legacyId): Reservation
{
    return Reservation::query()->findOrFail(DB::table('legacy_id_maps')->where('entity', $entity)->where('legacy_id', $legacyId)->value('new_id'));
}

beforeEach(function (): void {
    useLegacyServiceSchema();

    CenterFactory::new()->create(['id' => 6]);
    ClientFactory::new()->create(['id' => 50]);

    $training = ServiceFactory::new()->create(['id' => 20, 'center_id' => 6]);
    $this->fortyMinutes = PriceOptionFactory::new()->create(['service_id' => 20, 'unit' => 'minute', 'quantity' => 40, 'name' => null]);
    $this->package = ServicePackageFactory::new()->create(['price_option_id' => PriceOptionFactory::new()->create(['service_id' => 20, 'unit' => 'minute', 'quantity' => 45, 'name' => null])->id]);

    $event = EventFactory::new()->create(['id' => 7, 'start_date' => '2025-02-01', 'end_date' => '2025-02-03']);
    $eventService = ServiceFactory::new()->forServiceable($event, ServiceType::Event)->create(['center_id' => 6]);
    $this->vip = PriceOptionFactory::new()->create(['service_id' => $eventService->id, 'name' => 'VIP', 'quantity' => null]);

    $resort = ResortFactory::new()->create(['id' => 30]);
    ServiceFactory::new()->forServiceable($resort, ServiceType::Resort)->create(['id' => 30, 'center_id' => 6]);
    $this->breakfast = PriceOptionFactory::new()->create(['service_id' => 30, 'name' => 'Breakfast', 'price' => 5000, 'quantity' => null]);

    $membership = MembershipFactory::new()->create(['id' => 4, 'duration' => 2]);
    $membershipService = ServiceFactory::new()->forServiceable($membership, ServiceType::Membership)->create(['center_id' => 6]);

    DB::table('legacy_id_maps')->insert([
        ['entity' => 'service_package', 'legacy_id' => 300, 'new_id' => $this->package->id],
        ['entity' => 'service:event', 'legacy_id' => 7, 'new_id' => $eventService->id],
        ['entity' => 'service:membership', 'legacy_id' => 4, 'new_id' => $membershipService->id],
    ]);

    $legacy = DB::connection('legacy');
    $purchase = ['status' => 'completed', 'pay_when_arrived' => false, 'client_id' => 50, 'barn_id' => 6, 'created_by_type' => 'App\Models\Client', 'channel' => 'mobile', 'discount' => 0, 'tax_amount' => 0, 'order_id' => null, 'payment_information' => null, 'refunded_at' => null, 'canceled_at' => null, 'created_at' => '2025-01-01 10:00:00', 'updated_at' => '2025-01-01 10:00:00'];

    $legacy->table('purchases')->insert([
        [...$purchase, 'id' => 1, 'code' => 'ab12345678', 'source_type' => 'App\Models\Reservation', 'source_id' => 1001, 'total_price' => 100, 'price_after_discount' => 100, 'total_price_tax_added' => 100, 'order_id' => 'order-1', 'payment_information' => '{"company":"mada","method":"moyasar","type":"applepay"}'],
        [...$purchase, 'id' => 2, 'code' => 'ab22345678', 'source_type' => 'App\Models\ClientSessions', 'source_id' => 500, 'total_price' => 400, 'price_after_discount' => 400, 'total_price_tax_added' => 400, 'pay_when_arrived' => true, 'created_by_type' => 'App\Models\Barn'],
        [...$purchase, 'id' => 3, 'code' => 'ab32345678', 'source_type' => 'App\Models\Reservation', 'source_id' => 1002, 'total_price' => 0, 'price_after_discount' => 0, 'total_price_tax_added' => 0],
        [...$purchase, 'id' => 4, 'code' => 'ab42345678', 'status' => 'refunded', 'refunded_at' => '2025-01-03 09:00:00', 'source_type' => 'App\Models\EventReservation', 'source_id' => 70, 'total_price' => 100, 'price_after_discount' => 100, 'total_price_tax_added' => 100],
        [...$purchase, 'id' => 5, 'code' => 'ab52345678', 'status' => 'pending', 'source_type' => 'App\Models\ResortReservation', 'source_id' => 80, 'total_price' => 250, 'price_after_discount' => 250, 'total_price_tax_added' => 250],
        [...$purchase, 'id' => 6, 'code' => 'ab62345678', 'status' => 'canceled', 'canceled_at' => '2025-01-04 09:00:00', 'source_type' => 'App\Models\MembershipReservation', 'source_id' => 90, 'total_price' => 300, 'price_after_discount' => 300, 'total_price_tax_added' => 300],
        [...$purchase, 'id' => 7, 'code' => 'ab72345678', 'source_type' => 'App\Models\StablingOrder', 'source_id' => 1, 'total_price' => 1000, 'price_after_discount' => 1000, 'total_price_tax_added' => 1000],
    ]);

    $legacy->table('purchase_financial_snapshots')->insert([
        'purchase_id' => 1, 'purchase_amount' => 100, 'gateway_fee' => 2, 'platform_commission_percentage' => 3, 'platform_share' => 3, 'barn_share' => 95,
    ]);

    $reservation = ['service_id' => 20, 'tickets_booked' => 1, 'ticket_price' => null, 'clients_sessions_id' => null, 'created_at' => '2025-01-01 10:00:00', 'updated_at' => '2025-01-01 10:00:00'];

    $legacy->table('reservations')->insert([
        [...$reservation, 'id' => 1001, 'code' => 'abcd12345678', 'day' => '2025-01-05', 'time' => '10:00:00', 'duration' => 40, 'ticket_price' => 100, 'status' => 'finished', 'purchase_id' => 1],
        [...$reservation, 'id' => 1002, 'code' => 'abcd22345678', 'day' => '2099-01-05', 'time' => '23:30:00', 'duration' => 58, 'status' => 'active', 'clients_sessions_id' => 500, 'purchase_id' => 2],
        [...$reservation, 'id' => 1003, 'code' => 'abcd32345678', 'day' => '2025-01-06', 'time' => '10:00:00', 'duration' => 45, 'status' => 'canceled', 'clients_sessions_id' => 500, 'purchase_id' => 2],
    ]);

    $legacy->table('clients_sessions')->insert([
        ['id' => 500, 'session_id' => 300, 'count' => 8, 'reserved_count' => 5, 'end_date' => '2099-01-01 00:00:00', 'status' => 'active', 'purchase_id' => 2, 'membership_reservation_id' => null, 'knight_name' => 'Rider'],
        ['id' => 501, 'session_id' => 300, 'count' => 4, 'reserved_count' => 0, 'end_date' => '2099-01-01 00:00:00', 'status' => 'active', 'purchase_id' => 6, 'membership_reservation_id' => 90, 'knight_name' => null],
    ]);

    $legacy->table('event_reservations')->insert(['id' => 70, 'event_id' => 7, 'category' => '{"id":1,"name":"VIP","price":50}', 'tickets' => 2, 'date' => null]);
    $legacy->table('resort_additional_services')->insert(['id' => 2, 'service_id' => 30, 'name' => 'Breakfast', 'price' => 50]);
    $legacy->table('resort_reservations')->insert(['id' => 80, 'service_id' => 30, 'start_date' => '2099-12-01', 'end_date' => '2099-12-02', 'additional_services' => '[2]', 'status' => 'pending']);
    $legacy->table('membership_reservations')->insert(['id' => 90, 'membership_id' => 4, 'end_date' => '2025-03-01', 'daily_invitation_limit' => 5, 'status' => 'expired']);
});

test('single reservations keep their id, client, status, option and slot', function (): void {
    runBookingMigrators($this);

    $reservation = Reservation::query()->findOrFail(1001);

    expect($reservation->client_id)->toBe(50)
        ->and($reservation->center_id)->toBe(6)
        ->and($reservation->status)->toBe(ReservationStatus::Completed)
        ->and($reservation->price_option_id)->toBe($this->fortyMinutes->id)
        ->and($reservation->end_time)->toBe('10:40:00')
        ->and($reservation->total_amount)->toBe(10000)
        ->and($reservation->code)->toHaveLength(6)
        ->and($reservation->metadata['legacy_code'])->toBe('abcd12345678');
});

test('package sessions are free reservations on a recounted client package', function (): void {
    runBookingMigrators($this);

    $package = ClientPackage::query()->findOrFail(500);
    $session = Reservation::query()->findOrFail(1002);

    expect($package->remaining_sessions)->toBe(7)
        ->and($package->status)->toBe(ClientPackageStatus::Active)
        ->and($package->price)->toBe(40000)
        ->and($package->service_package_id)->toBe($this->package->id)
        ->and($package->knight_name)->toBe('Rider')
        ->and($session->client_package_id)->toBe(500)
        ->and($session->total_amount)->toBe(0)
        ->and($session->price_option_id)->toBeNull()
        ->and($session->metadata['legacy_duration'])->toBe(58)
        ->and($session->end_time)->toBe('24:00:00')
        ->and(Reservation::query()->findOrFail(1003)->status)->toBe(ReservationStatus::Cancelled);
});

test('a package session zero purchase is folded into the package purchase', function (): void {
    runBookingMigrators($this);

    expect(Purchase::query()->find(3))->toBeNull()
        ->and(DB::table('legacy_id_maps')->where('entity', 'purchase')->where('legacy_id', 3)->value('new_id'))->toBe(2)
        ->and(Purchase::query()->find(7))->toBeNull();
});

test('an online purchase gets a succeeded payment, its snapshot fee and ledger entries', function (): void {
    runBookingMigrators($this);

    $purchase = Purchase::query()->with(['items', 'payments', 'fee'])->findOrFail(1);
    $payment = $purchase->payments->sole();

    expect($purchase->status)->toBe(PurchaseStatus::Confirmed)
        ->and($purchase->source)->toBe(PurchaseSource::Client)
        ->and($purchase->channel)->toBe(PurchaseChannel::KhyyalApp)
        ->and($purchase->total_amount)->toBe(10000)
        ->and($purchase->metadata['legacy_code'])->toBe('ab12345678')
        ->and($purchase->items->sole()->purchasable_id)->toBe(1001)
        ->and($payment->method)->toBe(PaymentMethod::Online)
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->payment_company)->toBe('moyasar')
        ->and($payment->provider_fee_amount)->toBe(200)
        ->and($purchase->fee->amount)->toBe(300)
        ->and($purchase->fee->percentage_bps)->toBe(300)
        ->and(DB::table('center_ledger_entries')->where('purchase_id', 1)->pluck('amount', 'type')->all())
        ->toEqualCanonicalizing(['payment' => 10000, 'platform_fee' => -300]);
});

test('a center purchase paid on arrival gets a cash payment and a zero fee row', function (): void {
    runBookingMigrators($this);

    $purchase = Purchase::query()->with(['items', 'payments', 'fee'])->findOrFail(2);

    expect($purchase->source)->toBe(PurchaseSource::Center)
        ->and($purchase->channel)->toBe(PurchaseChannel::CenterDashboard)
        ->and($purchase->items->sole()->purchasable_type)->toBe('client_package')
        ->and($purchase->items->sole()->metadata)->toBe(['sessions' => 8])
        ->and($purchase->payments->sole()->method)->toBe(PaymentMethod::CashOnArrival)
        ->and($purchase->fee->amount)->toBe(0)
        ->and(DB::table('center_ledger_entries')->where('purchase_id', 2)->count())->toBe(0);
});

test('a refunded event purchase stays confirmed with a refunded payment and a cancelled booking', function (): void {
    runBookingMigrators($this);

    $reservation = mappedReservation('reservation:event', 70);
    $line = DB::table('reservation_lines')->where('reservation_id', $reservation->id)->sole();
    $purchase = Purchase::query()->with(['payments', 'fee'])->findOrFail(4);

    expect($reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->date->toDateString())->toBe('2025-02-01')
        ->and($reservation->end_date->toDateString())->toBe('2025-02-03')
        ->and($reservation->price_option_id)->toBe($this->vip->id)
        ->and([$line->kind, $line->quantity, $line->unit_price])->toBe(['ticket', 2, 5000])
        ->and($purchase->status)->toBe(PurchaseStatus::Confirmed)
        ->and($purchase->payments->sole()->status)->toBe(PaymentStatus::Refunded)
        ->and($purchase->payments->sole()->refunded_amount)->toBe(10000)
        ->and($purchase->fee->reversed_at)->not->toBeNull()
        ->and(DB::table('center_ledger_entries')->where('purchase_id', 4)->pluck('amount', 'type')->all())->toEqualCanonicalizing(['payment' => 10000, 'refund' => -10000]);
});

test('a pending resort purchase keeps a pending stay with its addon line', function (): void {
    runBookingMigrators($this);

    $reservation = mappedReservation('reservation:resort', 80);

    expect($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->subtotal)->toBe(25000)
        ->and($reservation->unit_price)->toBe(20000)
        ->and(DB::table('reservation_lines')->where('reservation_id', $reservation->id)->value('price_option_id'))->toBe($this->breakfast->id)
        ->and(Purchase::query()->findOrFail(5)->payments()->sole()->status)->toBe(PaymentStatus::Pending);
});

test('a cancelled membership purchase cancels the membership and its free packages', function (): void {
    runBookingMigrators($this);

    $membership = ClientMembership::query()->findOrFail(90);
    $package = ClientPackage::query()->findOrFail(501);

    expect($membership->status)->toBe(ClientMembershipStatus::Cancelled)
        ->and($membership->max_visits_per_day)->toBe(5)
        ->and($membership->price)->toBe(30000)
        ->and($package->client_membership_id)->toBe(90)
        ->and($package->price)->toBe(0)
        ->and($package->status)->toBe(ClientPackageStatus::Cancelled)
        ->and(Purchase::query()->findOrFail(6)->payments()->sole()->status)->toBe(PaymentStatus::Cancelled);
});

test('re-running creates nothing new', function (): void {
    runBookingMigrators($this);
    runBookingMigrators($this);

    expect(DB::table('reservations')->count())->toBe(5)
        ->and(DB::table('client_packages')->count())->toBe(2)
        ->and(DB::table('purchases')->count())->toBe(5)
        ->and(DB::table('payments')->count())->toBe(5)
        ->and(DB::table('purchase_fees')->count())->toBe(5)
        ->and(DB::table('center_ledger_entries')->count())->toBe(4);
});

test('a dry run writes nothing', function (): void {
    runBookingMigrators($this, ['--dry-run' => true]);

    expect(DB::table('reservations')->count())->toBe(0)
        ->and(DB::table('purchases')->count())->toBe(0)
        ->and(DB::table('center_ledger_entries')->count())->toBe(0);
});
