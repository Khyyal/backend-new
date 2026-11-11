<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Settlements\Database\Factories\CenterLedgerEntryFactory;
use Modules\Settlements\Database\Factories\CenterPayoutFactory;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Enums\CenterPayoutStatus;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Notifications\CenterPayoutPaidNotification;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-08 00:00:00');

    $this->center = CenterFactory::new()->create([
        'payout_iban' => 'SA0380000000608010167519',
        'payout_account_name' => 'Desert Stables',
        'payout_bank_name' => 'Al Rajhi Bank',
    ]);

    $this->entry = fn (int $amount, string $availableAt, array $attributes = []) => CenterLedgerEntryFactory::new()->create([
        'center_id' => $this->center->id,
        'amount' => $amount,
        'available_at' => $availableAt,
        ...$attributes,
    ]);

    $this->generate = fn () => $this->artisan('purchase:generate-payouts')->assertSuccessful();

    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ManagePayouts])->create();
});

test('the weekly run pays available credits net of debits and leaves held entries', function (): void {
    ($this->entry)(10000, '2026-11-01 10:00:00');
    ($this->entry)(-1000, '2026-11-07 10:00:00', ['type' => CenterLedgerEntryType::PlatformFee]);
    $held = ($this->entry)(5000, '2026-11-09 10:00:00');

    ($this->generate)();

    $payout = CenterPayout::query()->sole();
    expect($payout->amount)->toBe(9000)
        ->and($payout->entries_count)->toBe(2)
        ->and($payout->status)->toBe(CenterPayoutStatus::Pending)
        ->and($payout->iban)->toBe('SA0380000000608010167519')
        ->and($payout->period_end->toDateTimeString())->toBe('2026-11-08 00:00:00')
        ->and($held->refresh()->payout_id)->toBeNull();
});

test('a negative or zero balance carries forward', function (): void {
    ($this->entry)(-3000, '2026-11-01 10:00:00', ['type' => CenterLedgerEntryType::PlatformFee]);
    ($this->entry)(2000, '2026-11-02 10:00:00');

    ($this->generate)();

    expect(CenterPayout::query()->count())->toBe(0)
        ->and(CenterLedgerEntry::query()->whereNotNull('payout_id')->count())->toBe(0);

    ($this->entry)(5000, '2026-11-10 10:00:00');
    $this->travelTo('2026-11-15 00:00:00');
    ($this->generate)();

    expect(CenterPayout::query()->sole()->amount)->toBe(4000);
});

test('running twice for the same cutoff creates one payout', function (): void {
    ($this->entry)(10000, '2026-11-01 10:00:00');

    ($this->generate)();
    ($this->entry)(2000, '2026-11-02 10:00:00');
    ($this->generate)();

    expect(CenterPayout::query()->sole()->amount)->toBe(10000);
});

test('a center without a bank account still gets a payout with no account details', function (): void {
    $this->center->update(['payout_iban' => null, 'payout_account_name' => null, 'payout_bank_name' => null]);
    ($this->entry)(10000, '2026-11-01 10:00:00');

    ($this->generate)();

    $payout = CenterPayout::query()->sole();
    expect($payout->amount)->toBe(10000)
        ->and($payout->iban)->toBeNull()
        ->and($payout->account_name)->toBeNull()
        ->and($payout->bank_name)->toBeNull()
        ->and(CenterLedgerEntry::query()->where('payout_id', $payout->id)->count())->toBe(1);
});

test('admins mark a payout paid and the primary user is notified', function (): void {
    Notification::fake();
    $primary = UserFactory::new()->create();
    $this->center->users()->attach($primary->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);
    ($this->entry)(10000, '2026-11-01 10:00:00');
    ($this->generate)();
    $payout = CenterPayout::query()->sole();

    $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/payouts/{$payout->id}/mark-paid", ['reference' => 'TRX-123'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.reference', 'TRX-123');

    Notification::assertSentTo($primary, CenterPayoutPaidNotification::class);

    $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/payouts/{$payout->id}/cancel")
        ->assertUnprocessable();
});

test('cancelling a payout releases its entries to the next run', function (): void {
    ($this->entry)(10000, '2026-11-01 10:00:00');
    ($this->generate)();
    $payout = CenterPayout::query()->sole();

    $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/payouts/{$payout->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect(CenterLedgerEntry::query()->whereNotNull('payout_id')->count())->toBe(0);

    $this->travelTo('2026-11-15 00:00:00');
    ($this->generate)();

    expect(CenterPayout::query()->where('status', CenterPayoutStatus::Pending)->sole()->amount)->toBe(10000);
});

test('admin payout endpoints require payouts.manage', function (): void {
    $payout = CenterPayoutFactory::new()->create(['center_id' => $this->center->id]);

    $this->getJson('/api/v1/admin/payouts')->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ManageFees])->create(), 'system_user')
        ->postJson("/api/v1/admin/payouts/{$payout->id}/mark-paid", ['reference' => 'X'])
        ->assertForbidden();

    $this->actingAs($this->admin, 'system_user')
        ->getJson("/api/v1/admin/payouts?center_id={$this->center->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->admin, 'system_user')
        ->getJson("/api/v1/admin/centers/{$this->center->id}/ledger")
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['balance' => ['available', 'on_hold', 'in_pending_payouts', 'paid_out']]]);
});

test('center users with payouts.view see their payouts and balance', function (): void {
    $member = UserFactory::new()->create();
    $this->center->users()->attach($member->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    ($this->entry)(10000, '2026-11-01 10:00:00');
    ($this->entry)(3000, '2026-11-12 10:00:00');
    ($this->generate)();
    $payout = CenterPayout::query()->sole();
    $other = CenterPayoutFactory::new()->create();
    $base = "/api/v1/centers/{$this->center->id}";

    $this->actingAs($member, 'center_user')->getJson("{$base}/payouts")->assertForbidden();

    $member->syncCenterPermissions($this->center, [CenterUserPermission::ViewPayouts]);

    $this->actingAs($member, 'center_user')->getJson("{$base}/payouts")->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($member, 'center_user')->getJson("{$base}/payouts/{$payout->id}")->assertOk()->assertJsonCount(1, 'data.entries');
    $this->actingAs($member, 'center_user')->getJson("{$base}/payouts/{$other->id}")->assertNotFound();
    $this->actingAs($member, 'center_user')->getJson("{$base}/balance")
        ->assertOk()
        ->assertJsonPath('data.on_hold', 3000)
        ->assertJsonPath('data.in_pending_payouts', 10000)
        ->assertJsonPath('data.account.iban', 'SA0380000000608010167519');

    $this->actingAs($member, 'center_user')
        ->putJson("{$base}/payout-account", ['iban' => 'SA4420000001234567891234', 'account_name' => 'X', 'bank_name' => 'Y'])
        ->assertForbidden();
});

test('the primary user sets the payout account with a valid IBAN', function (): void {
    $primary = UserFactory::new()->create();
    $this->center->users()->attach($primary->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);
    $url = "/api/v1/centers/{$this->center->id}/payout-account";

    $this->actingAs($primary, 'center_user')
        ->putJson($url, ['iban' => 'GB33BUKB20201555555555', 'account_name' => 'X', 'bank_name' => 'Y'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('iban');

    $this->actingAs($primary, 'center_user')
        ->putJson($url, ['iban' => 'sa44 2000 0001 2345 6789 1234', 'account_name' => 'Desert Stables', 'bank_name' => 'SNB'])
        ->assertOk()
        ->assertJsonPath('data.iban', 'SA4420000001234567891234');
});

test('admins sort payouts and ledger entries and unknown sorts are rejected', function (): void {
    $smallPayout = CenterPayoutFactory::new()->create(['center_id' => $this->center->id, 'amount' => 1000, 'period_end' => '2026-11-01 00:00:00']);
    $largePayout = CenterPayoutFactory::new()->create(['center_id' => $this->center->id, 'amount' => 50000]);
    $credit = ($this->entry)(8000, '2026-11-01 00:00:00');
    $debit = ($this->entry)(-500, '2026-11-05 00:00:00');

    $this->actingAs($this->admin, 'system_user');
    $ids = fn (string $url): array => collect($this->getJson($url)->assertOk()->json('data'))->pluck('id')->all();
    $ledger = "/api/v1/admin/centers/{$this->center->id}/ledger";

    expect($ids('/api/v1/admin/payouts?sort=-amount'))->toBe([$largePayout->id, $smallPayout->id])
        ->and($ids('/api/v1/admin/payouts?sort=amount'))->toBe([$smallPayout->id, $largePayout->id])
        ->and($ids("{$ledger}?sort=amount"))->toBe([$debit->id, $credit->id])
        ->and($ids("{$ledger}?sort=-available_at"))->toBe([$debit->id, $credit->id])
        ->and($ids("{$ledger}?sort=available_at"))->toBe([$credit->id, $debit->id]);

    $this->getJson('/api/v1/admin/payouts?sort=status')->assertUnprocessable()->assertJsonValidationErrors('sort');
    $this->getJson("{$ledger}?sort=type")->assertUnprocessable()->assertJsonValidationErrors('sort');
});
