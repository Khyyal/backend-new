<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Exports\Database\Factories\ExportFactory;
use Modules\Exports\Enums\ExportStatus;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Models\Export;
use Modules\Exports\Notifications\ExportFailedNotification;
use Modules\Exports\Notifications\ExportReadyNotification;
use Modules\Exports\Services\ExportService;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Exports\AdminPurchasesExport;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    config(['exports.disk' => 'local', 'exports.retention_days' => 7]);

    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ExportPurchases])->create();

    $this->rows = fn (Export $export): array => IOFactory::load(Storage::disk('local')->path($export->path))
        ->getActiveSheet()
        ->toArray();
});

test('an export is queued, written with the filters, completed and the requester notified', function (): void {
    $center = CenterFactory::new()->create(['name' => 'Desert Riders']);
    $client = ClientFactory::new()->create(['first_name' => 'Aisha', 'last_name' => 'Noor']);
    $purchase = PurchaseFactory::new()->create([
        'buyer_type' => $client->getMorphClass(),
        'buyer_id' => $client->id,
        'merchant_type' => $center->getMorphClass(),
        'merchant_id' => $center->id,
        'status' => PurchaseStatus::Confirmed,
        'total_amount' => 12550,
        'created_at' => '2026-10-10 09:00:00',
    ]);
    PurchaseFactory::new()->create([
        'merchant_type' => $center->getMorphClass(),
        'merchant_id' => $center->id,
        'created_at' => '2026-09-01 09:00:00',
    ]);

    $response = $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/purchases/export', ['from' => '2026-10-01'])
        ->assertAccepted()
        ->assertJsonPath('data.type', 'admin_purchases')
        ->assertJsonPath('data.rows_count', 1)
        ->assertJsonPath('data.filters.from', '2026-10-01');

    $export = Export::query()->findOrFail($response->json('data.id'));

    expect($export->status)->toBe(ExportStatus::Completed)
        ->and($export->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());

    $rows = ($this->rows)($export);
    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('ID')
        ->and((int) $rows[1][0])->toBe($purchase->id)
        ->and($rows[1][2])->toBe('Desert Riders')
        ->and($rows[1][3])->toBe('Aisha Noor')
        ->and((float) $rows[1][12])->toBe(125.5);

    Notification::assertSentTo($this->admin, ExportReadyNotification::class, fn (ExportReadyNotification $notification): bool => $notification->export->is($export)
        && $notification->toArray($this->admin)['export_id'] === $export->id);
});

test('exporting needs the export permission, not only the view permission', function (): void {
    $viewer = SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyPurchases])->create();

    $this->actingAs($viewer, 'system_user')
        ->postJson('/api/v1/admin/purchases/export')
        ->assertForbidden();

    expect(Export::query()->count())->toBe(0);
});

test('a second export of the same kind is refused while the first is pending, unless it is stale', function (): void {
    $pending = ExportFactory::new()->requestedBy($this->admin)->create(['type' => ExportType::AdminPurchases]);

    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/purchases/export')
        ->assertTooManyRequests();

    $pending->forceFill(['created_at' => now()->subHours(2)])->save();

    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/purchases/export')
        ->assertAccepted();
});

test('a failed export is marked failed once and the requester is notified', function (): void {
    $export = ExportFactory::new()->requestedBy($this->admin)->create();
    $exportClass = new AdminPurchasesExport([]);
    $exportClass->exportId = $export->id;

    $exportClass->failed(new RuntimeException('disk full'));
    $exportClass->failed(new RuntimeException('disk full'));

    expect($export->refresh()->status)->toBe(ExportStatus::Failed)
        ->and($export->error)->toBe('disk full');

    Notification::assertSentToTimes($this->admin, ExportFailedNotification::class, 1);
    Notification::assertNotSentTo($this->admin, ExportReadyNotification::class);
});

test('only the requester downloads a completed, unexpired export', function (): void {
    $export = ExportFactory::new()->requestedBy($this->admin)->completed()->create();
    Storage::disk('local')->put($export->path, 'xlsx');
    $url = "/api/v1/admin/exports/{$export->id}/download";

    $this->actingAs($this->admin, 'system_user')->get($url)
        ->assertOk()
        ->assertDownload($export->file_name);

    $this->actingAs(SystemUserFactory::new()->superAdmin()->create(), 'system_user')->get($url)->assertForbidden();

    $export->update(['expires_at' => now()->subMinute()]);
    $this->actingAs($this->admin, 'system_user')->getJson($url)->assertStatus(410);

    $pending = ExportFactory::new()->requestedBy($this->admin)->create();
    $this->actingAs($this->admin, 'system_user')->getJson("/api/v1/admin/exports/{$pending->id}/download")->assertConflict();
});

test('the export list only shows my exports with a download link once completed', function (): void {
    $completed = ExportFactory::new()->requestedBy($this->admin)->completed()->create();
    $pending = ExportFactory::new()->requestedBy($this->admin)->create();
    ExportFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->getJson('/api/v1/admin/exports')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $pending->id)
        ->assertJsonPath('data.0.download_url', null)
        ->assertJsonPath('data.1.download_url', route('admin.exports.download', $completed));
});

test('center users only reach their own exports of the current center', function (): void {
    $center = CenterFactory::new()->create();
    $user = UserFactory::new()->create();
    $export = ExportFactory::new()->requestedBy($user)->completed()->create([
        'type' => ExportType::CenterClients,
        'center_id' => $center->id,
    ]);
    Storage::disk('local')->put($export->path, 'xlsx');

    $this->actingAs($user, 'center_user')
        ->getJson("/api/v1/centers/{$center->id}/exports")
        ->assertOk()
        ->assertJsonPath('data.0.download_url', route('centers.exports.download', ['center' => $center->id, 'export' => $export->id]));

    $this->actingAs($user, 'center_user')
        ->get("/api/v1/centers/{$center->id}/exports/{$export->id}/download")
        ->assertOk();

    $otherCenter = CenterFactory::new()->create();
    $this->actingAs($user, 'center_user')
        ->getJson("/api/v1/centers/{$otherCenter->id}/exports/{$export->id}")
        ->assertNotFound();
});

test('pruning deletes expired exports with their files and keeps live ones', function (): void {
    $expired = ExportFactory::new()->requestedBy($this->admin)->completed()->create(['expires_at' => now()->subDay()]);
    $live = ExportFactory::new()->requestedBy($this->admin)->completed()->create();
    Storage::disk('local')->put($expired->path, 'xlsx');
    Storage::disk('local')->put($live->path, 'xlsx');

    $this->artisan('exports:prune')->assertSuccessful();

    expect(Export::query()->pluck('id')->all())->toBe([$live->id]);
    Storage::disk('local')->assertMissing($expired->path);
    Storage::disk('local')->assertExists($live->path);
});

test('the service refuses to stream a missing file', function (): void {
    $export = ExportFactory::new()->requestedBy($this->admin)->completed()->create();

    app(ExportService::class)->download($export);
})->throws(GoneHttpException::class);
