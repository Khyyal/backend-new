<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Enums\FundedBy;
use Modules\Settlements\Actions\SyncPurchaseLedger;
use Modules\Purchase\Database\Factories\FeeRuleFactory;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Settlements\Services\PurchaseFinancials;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');
    config(['settlements.hold_days' => 7]);

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center);
    $this->option = $this->service->priceOptions()->first();
    $this->option->update(['price' => 10000]);
    FeeRuleFactory::new()->create(['percentage_bps' => 1000]);

    $this->body = [
        'service_id' => $this->service->id,
        'date' => '2026-10-26',
        'start_time' => '10:00',
        'price_option_id' => $this->option->id,
        'payment_method' => 'online',
    ];

    $this->payOnline = function (): Purchase {
        $this->actingAs($this->client, 'client')
            ->postJson('/api/v1/clients/reservations', $this->body)
            ->assertCreated();

        $purchase = Purchase::query()->latest('id')->first();
        $purchase->latestPayment->update(['payment_company' => 'moyasar']);
        app(PaymentStateService::class)->markSucceeded($purchase->latestPayment);

        return $purchase->refresh();
    };

    $this->entries = fn (): array => CenterLedgerEntry::query()->orderBy('id')->get()
        ->map(fn (CenterLedgerEntry $entry): array => [$entry->type, $entry->amount])
        ->all();
});

test('an online sale credits the payment after the hold period and debits the fee', function (): void {
    ($this->payOnline)();

    expect(($this->entries)())->toBe([
        [CenterLedgerEntryType::Payment, 10000],
        [CenterLedgerEntryType::PlatformFee, -1000],
    ]);

    $payment = CenterLedgerEntry::query()->where('type', CenterLedgerEntryType::Payment)->sole();
    expect($payment->available_at->toDateTimeString())->toBe('2026-10-27 08:00:00')
        ->and($payment->center_id)->toBe($this->center->id);
});

test('a cash sale only debits the fee, so the center owes the platform', function (): void {
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

    $purchase = Purchase::query()->sole();
    app(PaymentStateService::class)->markSucceeded($purchase->latestPayment);

    expect(($this->entries)())->toBe([[CenterLedgerEntryType::PlatformFee, -1000]]);
});

test('the center is credited the discount the platform funded', function (): void {
    PromotionFactory::new()->active()->forCenter($this->center)->percentage(2000)->create(['funded_by' => FundedBy::Platform->value]);

    $purchase = ($this->payOnline)();

    expect(($this->entries)())->toEqualCanonicalizing([
        [CenterLedgerEntryType::Payment, 8000],
        [CenterLedgerEntryType::PlatformFee, -1000],
        [CenterLedgerEntryType::PlatformSubsidy, 2000],
    ])->and(app(PurchaseFinancials::class)->for($purchase)['center_liability'])->toBe(9000);
});

test('a partial refund is debited and the fee kept; a full refund also gives back the fee', function (): void {
    $purchase = ($this->payOnline)();
    $payments = app(PaymentStateService::class);

    $payment = $payments->recordRefund($purchase->latestPayment, 4000);
    expect(CenterLedgerEntry::query()->sum('amount'))->toBe(10000 - 1000 - 4000);

    $payments->recordRefund($payment, 6000);

    expect(($this->entries)())->toBe([
        [CenterLedgerEntryType::Payment, 10000],
        [CenterLedgerEntryType::PlatformFee, -1000],
        [CenterLedgerEntryType::Refund, -4000],
        [CenterLedgerEntryType::Refund, -6000],
        [CenterLedgerEntryType::FeeReversal, 1000],
    ])->and(CenterLedgerEntry::query()->sum('amount'))->toBe(0);
});

test('cancelling a confirmed purchase gives back the fee', function (): void {
    $purchase = ($this->payOnline)();

    app(PurchaseStateService::class)->cancel($purchase);

    expect(CenterLedgerEntry::query()->where('type', CenterLedgerEntryType::FeeReversal)->sum('amount'))->toBe(1000);
});

test('syncing again posts nothing new', function (): void {
    $purchase = ($this->payOnline)();
    app(PaymentStateService::class)->recordRefund($purchase->latestPayment, 3000);
    $count = CenterLedgerEntry::query()->count();

    app(SyncPurchaseLedger::class)->execute($purchase->id);
    app(SyncPurchaseLedger::class)->execute($purchase->id);

    expect(CenterLedgerEntry::query()->count())->toBe($count);
});

test('financials split the purchase between center, platform and provider', function (): void {
    config(['purchase.provider_fees.moyasar' => ['percentage_bps' => 200, 'fixed_amount' => 0]]);

    $purchase = ($this->payOnline)();

    expect(app(PurchaseFinancials::class)->for($purchase))->toBe([
        'gross_paid' => 10000,
        'refunded' => 0,
        'provider_fee' => 200,
        'platform_fee' => 1000,
        'platform_subsidy' => 0,
        'center_liability' => 9000,
        'platform_net_revenue' => 800,
    ]);
});
