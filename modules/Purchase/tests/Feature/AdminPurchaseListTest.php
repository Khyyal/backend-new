<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Merchant\Platform;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function centerPurchase(object $center, object $client, array $attributes = []): object
{
    return PurchaseFactory::new()->create([
        'buyer_type' => $client->getMorphClass(),
        'buyer_id' => $client->getKey(),
        'merchant_type' => $center->getMorphClass(),
        'merchant_id' => $center->getKey(),
        'source' => PurchaseSource::Client,
        'status' => PurchaseStatus::Confirmed,
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->url = '/api/v1/admin/purchases';
});

test('guests are unauthenticated and users without the permission are forbidden', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')->getJson($this->url)->assertForbidden();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyPurchases])->create(), 'system_user')
        ->getJson($this->url)->assertOk();
});

test('only client purchases from centers are listed, never center purchases from the platform', function (): void {
    $center = CenterFactory::new()->create();
    $client = ClientFactory::new()->create();
    $purchase = centerPurchase($center, $client);

    PurchaseFactory::new()->create([
        'buyer_type' => $center->getMorphClass(),
        'buyer_id' => $center->getKey(),
        'merchant_type' => (new Platform)->getMorphClass(),
        'merchant_id' => 0,
    ]);

    $this->actingAs($this->admin, 'system_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $purchase->id)
        ->assertJsonPath('data.0.center.id', $center->id)
        ->assertJsonPath('data.0.client.id', $client->id)
        ->assertJsonPath('data.0.total_amount', $purchase->total_amount);
});

test('filters by center, client, name or phone, status, source and date', function (): void {
    $centerA = CenterFactory::new()->create();
    $centerB = CenterFactory::new()->create();
    $aisha = ClientFactory::new()->create(['first_name' => 'Aisha', 'last_name' => 'Noor', 'phone_number' => '500000001']);
    $omar = ClientFactory::new()->create(['first_name' => 'Omar', 'last_name' => 'Ali', 'phone_number' => '500000002']);

    $first = centerPurchase($centerA, $aisha, ['created_at' => '2026-01-10 10:00:00']);
    $second = centerPurchase($centerB, $omar, ['status' => PurchaseStatus::Cancelled, 'source' => PurchaseSource::Center, 'created_at' => '2026-03-10 10:00:00']);

    $this->actingAs($this->admin, 'system_user');

    $ids = fn (string $query): array => collect($this->getJson("{$this->url}?{$query}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids("center_id={$centerA->id}"))->toBe([$first->id])
        ->and($ids("client_id={$omar->id}"))->toBe([$second->id])
        ->and($ids('q='.urlencode('Aisha Noor')))->toBe([$first->id])
        ->and($ids('q=500000002'))->toBe([$second->id])
        ->and($ids('status=cancelled'))->toBe([$second->id])
        ->and($ids('source=center'))->toBe([$second->id])
        ->and($ids('from=2026-02-01'))->toBe([$second->id])
        ->and($ids('to=2026-02-01'))->toBe([$first->id])
        ->and($ids('from=2026-01-01&to=2026-12-31'))->toBe([$second->id, $first->id]);

    $this->getJson("{$this->url}?status=nope")->assertUnprocessable();
    $this->getJson("{$this->url}?from=2026-05-01&to=2026-01-01")->assertUnprocessable();
});

test('filters by the latest payment method and status', function (): void {
    $center = CenterFactory::new()->create();
    $client = ClientFactory::new()->create();
    $paid = centerPurchase($center, $client);
    $cash = centerPurchase($center, $client);

    PaymentFactory::new()->create(['purchase_id' => $paid->id, 'method' => PaymentMethod::Online, 'status' => PaymentStatus::Succeeded]);
    PaymentFactory::new()->create(['purchase_id' => $cash->id, 'method' => PaymentMethod::CashOnArrival, 'status' => PaymentStatus::Pending]);

    $this->actingAs($this->admin, 'system_user');

    $this->getJson("{$this->url}?payment_method=online")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $paid->id)->assertJsonPath('data.0.payment_status', 'succeeded');
    $this->getJson("{$this->url}?payment_status=pending")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cash->id);
});

test('shows a client purchase with items and payments but hides center-to-platform purchases', function (): void {
    $center = CenterFactory::new()->create();
    $client = ClientFactory::new()->create();
    $purchase = centerPurchase($center, $client);
    PurchaseItemFactory::new()->create(['purchase_id' => $purchase->id]);
    PaymentFactory::new()->create(['purchase_id' => $purchase->id, 'method' => PaymentMethod::Online, 'status' => PaymentStatus::Succeeded]);

    $subscription = PurchaseFactory::new()->create([
        'buyer_type' => $center->getMorphClass(),
        'buyer_id' => $center->getKey(),
        'merchant_type' => (new Platform)->getMorphClass(),
        'merchant_id' => 0,
    ]);

    $this->getJson("{$this->url}/{$purchase->id}")->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')->getJson("{$this->url}/{$purchase->id}")->assertForbidden();

    $this->actingAs($this->admin, 'system_user');
    $this->getJson("{$this->url}/{$purchase->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $purchase->id)
        ->assertJsonPath('data.client.id', $client->id)
        ->assertJsonPath('data.center.id', $center->id)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonPath('data.payments.0.status', 'succeeded');

    $this->getJson("{$this->url}/{$subscription->id}")->assertNotFound();
});

test('sorts by total or creation date and rejects unknown sorts', function (): void {
    $center = CenterFactory::new()->create();
    $client = ClientFactory::new()->create();
    $small = centerPurchase($center, $client, ['total_amount' => 5000, 'created_at' => '2026-03-10 10:00:00']);
    $large = centerPurchase($center, $client, ['total_amount' => 90000, 'created_at' => '2026-01-10 10:00:00']);

    $this->actingAs($this->admin, 'system_user');
    $ids = fn (string $sort): array => collect($this->getJson("{$this->url}?sort={$sort}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('-total_amount'))->toBe([$large->id, $small->id])
        ->and($ids('total_amount'))->toBe([$small->id, $large->id])
        ->and($ids('created_at'))->toBe([$large->id, $small->id]);

    $this->getJson("{$this->url}?sort=status")->assertUnprocessable()->assertJsonValidationErrors('sort');
});
