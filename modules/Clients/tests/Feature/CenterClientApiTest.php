<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->url = "/api/v1/centers/{$this->center->id}/clients";

    $this->purchase = fn ($client, string $at, PurchaseStatus $status = PurchaseStatus::Confirmed, $center = null) => PurchaseFactory::new()->create([
        'buyer_type' => $client->getMorphClass(),
        'buyer_id' => $client->id,
        'merchant_type' => ($center ?? $this->center)->getMorphClass(),
        'merchant_id' => ($center ?? $this->center)->id,
        'status' => $status,
        'created_at' => $at,
    ]);
});

test('guests and users of other centers are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();
});

test('lists the center buyers with their purchase summary', function (): void {
    $sara = ClientFactory::new()->create(['first_name' => 'Sara', 'last_name' => 'Belhadj']);
    $omar = ClientFactory::new()->create();
    $pendingOnly = ClientFactory::new()->create();
    $elsewhere = ClientFactory::new()->create();

    ($this->purchase)($sara, '2026-09-01 10:00:00');
    ($this->purchase)($sara, '2026-10-10 12:00:00', PurchaseStatus::Completed);
    ($this->purchase)($sara, '2026-10-15 12:00:00', PurchaseStatus::Cancelled);
    ($this->purchase)($omar, '2026-10-12 09:00:00');
    ($this->purchase)($pendingOnly, '2026-10-12 09:00:00', PurchaseStatus::Pending);
    ($this->purchase)($elsewhere, '2026-10-12 09:00:00', PurchaseStatus::Confirmed, CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $omar->id)
        ->assertJsonPath('data.0.purchases_count', 1)
        ->assertJsonPath('data.1.id', $sara->id)
        ->assertJsonPath('data.1.full_name', 'Sara Belhadj')
        ->assertJsonPath('data.1.purchases_count', 2)
        ->assertJsonPath('data.1.first_purchase_at', '2026-09-01T10:00:00+00:00')
        ->assertJsonPath('data.1.last_purchase_at', '2026-10-10T12:00:00+00:00');
});
