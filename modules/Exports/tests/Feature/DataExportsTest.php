<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Exports\Enums\ExportStatus;
use Modules\Exports\Models\Export;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Settlements\Database\Factories\CenterLedgerEntryFactory;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    config(['exports.disk' => 'local']);

    $this->center = CenterFactory::new()->create();

    /** Rows of the export the response queued, heading row first. */
    $this->sheet = function (TestResponse $response): array {
        $export = Export::query()->findOrFail($response->assertAccepted()->json('data.id'));
        expect($export->status)->toBe(ExportStatus::Completed);

        return IOFactory::load(Storage::disk('local')->path($export->path))->getActiveSheet()->toArray();
    };

    $this->ids = fn (array $rows): array => collect($rows)->skip(1)->map(fn (array $row): int => (int) $row[0])->sort()->values()->all();

    $this->centerPurchase = fn (array $attributes = []): Purchase => PurchaseFactory::new()->create([
        'merchant_type' => $this->center->getMorphClass(),
        'merchant_id' => $this->center->id,
        'status' => PurchaseStatus::Confirmed,
        ...$attributes,
    ]);
});

test('admins export centers with the list filters', function (): void {
    $admin = SystemUserFactory::new()->withPermissions([SystemPermission::ExportCenters])->create();
    $match = CenterFactory::new()->create(['name' => 'Golden Saddle']);
    CenterFactory::new()->create(['name' => 'Other']);

    $rows = ($this->sheet)($this->actingAs($admin, 'system_user')->postJson('/api/v1/admin/centers/export', ['search' => 'Golden']));

    expect(($this->ids)($rows))->toBe([$match->id])
        ->and($rows[1][1])->toBe('Golden Saddle');

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyCenters])->create(), 'system_user')
        ->postJson('/api/v1/admin/centers/export')
        ->assertForbidden();
});

test('admins export clients with the list filters', function (): void {
    $admin = SystemUserFactory::new()->withPermissions([SystemPermission::ExportClients])->create();
    $recent = ClientFactory::new()->create(['created_at' => '2026-10-05 10:00:00']);
    ClientFactory::new()->create(['created_at' => '2026-08-05 10:00:00']);

    $rows = ($this->sheet)($this->actingAs($admin, 'system_user')->postJson('/api/v1/admin/clients/export', ['from' => '2026-10-01']));

    expect(($this->ids)($rows))->toBe([$recent->id]);

    $this->actingAs($admin, 'system_user')
        ->postJson('/api/v1/admin/clients/export', ['from' => '2026-10-01', 'to' => '2026-09-01'])
        ->assertUnprocessable();
});

test('admins export one center purchases with every amount', function (): void {
    $admin = SystemUserFactory::new()->withPermissions([SystemPermission::ExportPurchases])->create();
    $purchase = ($this->centerPurchase)();
    PurchaseFactory::new()->create(['merchant_type' => $this->center->getMorphClass(), 'merchant_id' => CenterFactory::new()->create()->id]);

    $response = $this->actingAs($admin, 'system_user')->postJson("/api/v1/admin/centers/{$this->center->id}/purchases/export");
    $response->assertJsonPath('data.type', 'admin_center_purchases')->assertJsonPath('data.center_id', $this->center->id);
    $rows = ($this->sheet)($response);

    expect(($this->ids)($rows))->toBe([$purchase->id])
        ->and($rows[0])->toContain('Total (SAR)');
});

test('admins export a center ledger with the ledger filters', function (): void {
    $admin = SystemUserFactory::new()->withPermissions([SystemPermission::ExportCenterLedgers])->create();
    $fee = CenterLedgerEntryFactory::new()->create(['center_id' => $this->center->id, 'type' => CenterLedgerEntryType::PlatformFee, 'amount' => -1250]);
    CenterLedgerEntryFactory::new()->create(['center_id' => $this->center->id, 'type' => CenterLedgerEntryType::Payment]);
    CenterLedgerEntryFactory::new()->create(['type' => CenterLedgerEntryType::PlatformFee]);

    $rows = ($this->sheet)($this->actingAs($admin, 'system_user')->postJson("/api/v1/admin/centers/{$this->center->id}/ledger/export", ['type' => 'platform_fee']));

    expect(($this->ids)($rows))->toBe([$fee->id])
        ->and((float) $rows[1][5])->toBe(-12.5);

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ManagePayouts])->create(), 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/ledger/export")
        ->assertForbidden();
});

describe('center users', function (): void {
    beforeEach(function (): void {
        $this->user = UserFactory::new()->create();
        $this->center->users()->attach($this->user->id, [
            'status' => ActivationStatus::ACTIVE->value,
            'joined_at' => now()->toDateString(),
            'is_primary' => false,
        ]);
        $this->training = ServiceFactory::new()->create(['center_id' => $this->center->id, 'type' => ServiceType::Training]);
        $this->visit = ServiceFactory::new()->create(['center_id' => $this->center->id, 'type' => ServiceType::Visit]);

        $this->purchaseOf = function ($service): Purchase {
            $purchase = ($this->centerPurchase)();
            PurchaseItemFactory::new()->create([
                'purchase_id' => $purchase->id,
                'purchasable_type' => (new Reservation)->getMorphClass(),
                'purchasable_id' => ReservationFactory::new()->create(['service_id' => $service->id, 'trainer_id' => null])->id,
            ]);

            return $purchase;
        };
        $this->purchasesUrl = "/api/v1/centers/{$this->center->id}/purchases/export";
    });

    test('purchase exports need purchases.export and follow purchase visibility and price hiding', function (): void {
        $training = ($this->purchaseOf)($this->training);
        ($this->purchaseOf)($this->visit);
        $visible = [CenterUserPermission::ViewTrainingPurchases, CenterUserPermission::ViewUnassignedPurchases];

        $this->user->syncCenterPermissions($this->center, $visible);
        $this->actingAs($this->user, 'center_user')->postJson($this->purchasesUrl)->assertForbidden();

        $this->user->syncCenterPermissions($this->center, [...$visible, CenterUserPermission::ExportPurchases]);
        $rows = ($this->sheet)($this->actingAs($this->user, 'center_user')->postJson($this->purchasesUrl));

        expect(($this->ids)($rows))->toBe([$training->id])
            ->and($rows[0])->not->toContain('Total (SAR)');

        $this->user->syncCenterPermissions($this->center, [...$visible, CenterUserPermission::ExportPurchases, CenterUserPermission::ViewPurchasePrices]);
        $this->travel(1)->seconds();
        $rows = ($this->sheet)($this->actingAs($this->user, 'center_user')->postJson($this->purchasesUrl, ['status' => 'confirmed']));

        expect($rows[0])->toContain('Total (SAR)');
    });

    test('client exports need clients.export and only list the center clients', function (): void {
        $url = "/api/v1/centers/{$this->center->id}/clients/export";
        $buyer = ClientFactory::new()->create();
        ($this->centerPurchase)(['buyer_type' => $buyer->getMorphClass(), 'buyer_id' => $buyer->id]);
        ClientFactory::new()->create();

        $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
        $this->actingAs($this->user, 'center_user')->postJson($url)->assertForbidden();

        $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations, CenterUserPermission::ExportClients]);
        $response = $this->actingAs($this->user, 'center_user')->postJson($url);
        $response->assertJsonPath('data.download_url', fn (string $url): bool => str_contains($url, "/centers/{$this->center->id}/exports/"));
        $rows = ($this->sheet)($response);

        expect(($this->ids)($rows))->toBe([$buyer->id])
            ->and((int) $rows[1][6])->toBe(1);
    });
});
