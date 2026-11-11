<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Models\Center;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Settlements\Database\Factories\CenterFinancialBaselineFactory;
use Modules\Settlements\Database\Factories\CenterLedgerEntryFactory;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Models\CenterFinancialBaseline;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Models\CenterPayout;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-11 08:00:00');
});

function baselineAdmin(): SystemUser
{
    return SystemUserFactory::new()->withPermissions([SystemPermission::ManageFinancialBaselines])->create();
}

/**
 * A confirmed center purchase with its ledger entries, as the purchases
 * migrator leaves a legacy sale.
 *
 * @param  array<string, int>  $entries  Signed amounts keyed by entry type.
 */
function legacySale(Center $center, string $createdAt, int $total, array $entries): Purchase
{
    $purchase = PurchaseFactory::new()->create([
        'merchant_type' => $center->getMorphClass(),
        'merchant_id' => $center->id,
        'status' => PurchaseStatus::Confirmed,
        'total_amount' => $total,
        'created_at' => $createdAt,
    ]);

    foreach ($entries as $type => $amount) {
        CenterLedgerEntryFactory::new()->create([
            'center_id' => $center->id,
            'purchase_id' => $purchase->id,
            'type' => $type,
            'amount' => $amount,
            'available_at' => '2026-11-01 00:00:00',
        ]);
    }

    return $purchase;
}

/**
 * @return array<int, int>
 */
function baselineAdjustments(Center $center): array
{
    return CenterLedgerEntry::query()
        ->where('center_id', $center->id)
        ->where('type', CenterLedgerEntryType::BaselineAdjustment)
        ->orderBy('id')
        ->pluck('amount')
        ->all();
}

function baselineUrl(Center $center): string
{
    return "/api/v1/admin/centers/{$center->id}/financial-baseline";
}

test('the first baseline posts the difference to the confirmed balance and lifts the block', function (): void {
    $center = CenterFactory::new()->create();
    $admin = baselineAdmin();
    CenterFinancialBaselineFactory::new()->create(['center_id' => $center->id]);
    legacySale($center, '2026-10-01 10:00:00', 10000, ['payment' => 10000, 'platform_fee' => -1000]);

    $response = $this->actingAs($admin, 'system_user')->putJson(baselineUrl($center), [
        'cutoff_at' => '2026-11-10 00:00:00',
        'target_balance' => 2000,
        'target_paid_out' => 7000,
        'target_platform_fees' => 1200,
        'target_gross_sales' => 10500,
        'note' => 'Finance sheet.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.migrated_balance', 9000)
        ->assertJsonPath('data.migrated_platform_fees', 1000)
        ->assertJsonPath('data.migrated_gross_sales', 10000)
        ->assertJsonPath('data.balance_adjustment', -7000)
        ->assertJsonPath('data.platform_fees_delta', 200)
        ->assertJsonPath('data.gross_sales_delta', 500)
        ->assertJsonPath('data.refunds_delta', null)
        ->assertJsonPath('meta.balance', ['available' => 2000, 'on_hold' => 0, 'in_pending_payouts' => 0, 'paid_out' => 7000, 'baseline_pending' => false]);

    $entry = CenterLedgerEntry::query()->where('type', CenterLedgerEntryType::BaselineAdjustment)->sole();
    expect($entry->amount)->toBe(-7000)
        ->and($entry->purchase_id)->toBeNull()
        ->and($entry->available_at->toDateTimeString())->toBe('2026-11-11 08:00:00');

    $baseline = CenterFinancialBaseline::query()->where('center_id', $center->id)->sole();
    expect($baseline->confirmed_by)->toBe($admin->id)
        ->and($baseline->confirmed_at->toDateTimeString())->toBe('2026-11-11 08:00:00')
        ->and($baseline->cutoff_at->toDateTimeString())->toBe('2026-11-10 00:00:00')
        ->and($baseline->note)->toBe('Finance sheet.');
});

test('purchases created after the cutoff and entries already in a payout are left out of the migrated balance', function (): void {
    $center = CenterFactory::new()->create();
    legacySale($center, '2026-10-01 10:00:00', 10000, ['payment' => 10000]);
    legacySale($center, '2026-11-10 12:00:00', 4000, ['payment' => 4000]);
    $paid = legacySale($center, '2026-09-01 10:00:00', 6000, ['payment' => 6000]);
    $payout = CenterPayout::factory()->paid()->create(['center_id' => $center->id, 'amount' => 6000]);
    CenterLedgerEntry::query()->where('purchase_id', $paid->id)->update(['payout_id' => $payout->id]);

    $this->actingAs(baselineAdmin(), 'system_user')->putJson(baselineUrl($center), [
        'cutoff_at' => '2026-11-10 00:00:00',
        'target_balance' => 10000,
        'note' => 'Finance sheet.',
    ])
        ->assertOk()
        ->assertJsonPath('data.migrated_balance', 10000)
        ->assertJsonPath('data.migrated_gross_sales', 16000);

    expect(baselineAdjustments($center))->toBe([]);
});

test('setting a confirmed baseline again posts only the change in the target balance', function (): void {
    $center = CenterFactory::new()->create();
    CenterFinancialBaselineFactory::new()->confirmed()->create([
        'center_id' => $center->id,
        'cutoff_at' => '2026-11-10 00:00:00',
        'target_balance' => 2000,
        'migrated_balance' => 9000,
    ]);
    legacySale($center, '2026-10-01 10:00:00', 10000, ['payment' => 10000, 'platform_fee' => -1000]);

    $this->actingAs(baselineAdmin(), 'system_user')->putJson(baselineUrl($center), [
        'target_balance' => 2500,
        'note' => 'Corrected by finance.',
    ])
        ->assertOk()
        ->assertJsonPath('data.target_balance', 2500)
        ->assertJsonPath('data.migrated_balance', 9000)
        ->assertJsonPath('data.cutoff_at', '2026-11-10T00:00:00+00:00');

    expect(baselineAdjustments($center))->toBe([500]);
});

test('setting the same target balance again posts nothing', function (): void {
    $center = CenterFactory::new()->create();
    CenterFinancialBaselineFactory::new()->confirmed()->create(['center_id' => $center->id, 'target_balance' => 2000]);

    $this->actingAs(baselineAdmin(), 'system_user')->putJson(baselineUrl($center), [
        'target_balance' => 2000,
        'note' => 'No change.',
    ])->assertOk();

    expect(baselineAdjustments($center))->toBe([]);
});

test('a refund on a legacy purchase after confirmation is not undone by setting the baseline again', function (): void {
    $center = CenterFactory::new()->create();
    $admin = baselineAdmin();
    $purchase = legacySale($center, '2026-10-01 10:00:00', 10000, ['payment' => 10000]);
    $figures = ['cutoff_at' => '2026-11-10 00:00:00', 'target_balance' => 4000, 'note' => 'Finance sheet.'];
    $this->actingAs($admin, 'system_user')->putJson(baselineUrl($center), $figures)->assertOk();
    CenterLedgerEntryFactory::new()->create([
        'center_id' => $center->id,
        'purchase_id' => $purchase->id,
        'type' => CenterLedgerEntryType::Refund,
        'amount' => -3000,
    ]);

    $response = $this->actingAs($admin, 'system_user')->putJson(baselineUrl($center), $figures);

    $response->assertOk()->assertJsonPath('meta.balance.available', 1000);
    expect(baselineAdjustments($center))->toBe([-6000]);
});

test('a center without a baseline row can be given one', function (): void {
    $center = CenterFactory::new()->create();

    $this->actingAs(baselineAdmin(), 'system_user')->putJson(baselineUrl($center), [
        'cutoff_at' => '2026-11-10 00:00:00',
        'target_balance' => -1500,
        'note' => 'Center owed fees on cash sales.',
    ])->assertOk()->assertJsonPath('data.status', 'confirmed');

    expect(baselineAdjustments($center))->toBe([-1500]);
});

test('changing the cutoff of a confirmed baseline returns 422 and posts nothing', function (): void {
    $center = CenterFactory::new()->create();
    CenterFinancialBaselineFactory::new()->confirmed()->create(['center_id' => $center->id, 'cutoff_at' => '2026-11-10 00:00:00']);

    $this->actingAs(baselineAdmin(), 'system_user')->putJson(baselineUrl($center), [
        'cutoff_at' => '2026-11-09 00:00:00',
        'target_balance' => 5000,
        'note' => 'Moved cutoff.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cutoff_at' => 'The cutoff of a confirmed baseline cannot be changed.']);

    expect(baselineAdjustments($center))->toBe([])
        ->and(CenterFinancialBaseline::query()->sole()->target_balance)->toBe(0);
});

test('the first baseline requires a cutoff, a target balance and a note', function (): void {
    $center = CenterFactory::new()->create();

    $this->actingAs(baselineAdmin(), 'system_user')->putJson(baselineUrl($center), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'cutoff_at' => 'The cutoff at field is required.',
            'target_balance' => 'The target balance field is required.',
            'note' => 'The note field is required.',
        ]);

    expect(CenterFinancialBaseline::query()->count())->toBe(0);
});

test('invalid figures return 422', function (array $override, string $field, string $message): void {
    $center = CenterFactory::new()->create();

    $this->actingAs(baselineAdmin(), 'system_user')->putJson(baselineUrl($center), [
        'cutoff_at' => '2026-11-10 00:00:00',
        'target_balance' => 1000,
        'note' => 'Finance sheet.',
        ...$override,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);

    expect(baselineAdjustments($center))->toBe([]);
})->with([
    'a cutoff in the future' => [['cutoff_at' => '2026-11-12 00:00:00'], 'cutoff_at', 'The cutoff at field must be a date before or equal to now.'],
    'a fractional balance' => [['target_balance' => 10.5], 'target_balance', 'The target balance field must be an integer.'],
    'a negative paid out total' => [['target_paid_out' => -1], 'target_paid_out', 'The target paid out field must be at least 0.'],
    'a negative fees total' => [['target_platform_fees' => -1], 'target_platform_fees', 'The target platform fees field must be at least 0.'],
    'a note over 500 characters' => [['note' => str_repeat('a', 501)], 'note', 'The note field must not be greater than 500 characters.'],
]);

test('a pending baseline shows a live preview of the migrated figures', function (): void {
    $center = CenterFactory::new()->create();
    CenterFinancialBaselineFactory::new()->create(['center_id' => $center->id]);
    legacySale($center, '2026-10-01 10:00:00', 10000, ['payment' => 10000, 'platform_fee' => -1000, 'refund' => -2000]);

    $this->actingAs(baselineAdmin(), 'system_user')->getJson(baselineUrl($center))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.migrated_balance', 7000)
        ->assertJsonPath('data.migrated_platform_fees', 1000)
        ->assertJsonPath('data.migrated_refunds', 2000)
        ->assertJsonPath('data.target_balance', null)
        ->assertJsonPath('meta.balance.baseline_pending', true);
});

test('a center that needs no baseline is reported as not required', function (): void {
    $center = CenterFactory::new()->create();

    $this->actingAs(baselineAdmin(), 'system_user')->getJson(baselineUrl($center))
        ->assertOk()
        ->assertJsonPath('data.status', 'not_required')
        ->assertJsonPath('meta.balance.baseline_pending', false);
});

test('baseline endpoints return 401 without a token', function (): void {
    $center = CenterFactory::new()->create();

    $this->getJson(baselineUrl($center))->assertUnauthorized();
    $this->putJson(baselineUrl($center), [])->assertUnauthorized();
});

test('baseline endpoints return 403 without payouts.baseline', function (): void {
    $center = CenterFactory::new()->create();
    $admin = SystemUserFactory::new()->withPermissions([SystemPermission::ManagePayouts])->create();

    $this->actingAs($admin, 'system_user')->getJson(baselineUrl($center))->assertForbidden();
    $this->actingAs($admin, 'system_user')->putJson(baselineUrl($center), [
        'cutoff_at' => '2026-11-10 00:00:00',
        'target_balance' => 1000,
        'note' => 'Finance sheet.',
    ])->assertForbidden();

    expect(CenterFinancialBaseline::query()->count())->toBe(0);
});

test('the payout run skips a center whose baseline is pending and pays it once confirmed', function (): void {
    $center = CenterFactory::new()->create();
    $baseline = CenterFinancialBaselineFactory::new()->create(['center_id' => $center->id]);
    legacySale($center, '2026-10-01 10:00:00', 10000, ['payment' => 10000]);

    $this->artisan('purchase:generate-payouts', ['--cutoff' => '2026-11-08 00:00:00'])->assertSuccessful();

    expect(CenterPayout::query()->count())->toBe(0);

    $baseline->update(['confirmed_at' => now()]);
    $this->artisan('purchase:generate-payouts', ['--cutoff' => '2026-11-08 00:00:00'])->assertSuccessful();

    expect(CenterPayout::query()->sole()->amount)->toBe(10000);
});
