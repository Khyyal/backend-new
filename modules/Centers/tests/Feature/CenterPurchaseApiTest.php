<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
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
    $this->url = "/api/v1/centers/{$this->center->id}/purchases";

    $this->purchase = fn (array $attributes = [], $center = null) => PurchaseFactory::new()->create([
        'merchant_type' => ($center ?? $this->center)->getMorphClass(),
        'merchant_id' => ($center ?? $this->center)->id,
        'status' => PurchaseStatus::Confirmed,
        ...$attributes,
    ]);
});

test('guests and users of other centers are rejected', function (): void {
    $purchase = ($this->purchase)();

    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/{$purchase->id}")
        ->assertForbidden();
});

test('lists only the center purchases, newest first, with a status filter', function (): void {
    $old = ($this->purchase)(['created_at' => '2026-09-01 10:00:00']);
    $new = ($this->purchase)(['created_at' => '2026-10-01 10:00:00', 'status' => PurchaseStatus::Pending]);
    ($this->purchase)([], CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.1.client.id', $old->buyer_id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?status=pending")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $new->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?status=bogus")
        ->assertUnprocessable();
});

test('shows a purchase with its items and payments', function (): void {
    $purchase = ($this->purchase)();
    PurchaseItemFactory::new()->count(2)->create(['purchase_id' => $purchase->id]);
    PaymentFactory::new()->online()->succeeded()->create(['purchase_id' => $purchase->id, 'amount' => 100]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$purchase->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $purchase->id)
        ->assertJsonPath('data.items_count', 2)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonPath('data.payments.0.method', 'online')
        ->assertJsonPath('data.payments.0.status', 'succeeded')
        ->assertJsonMissingPath('data.payments.0.provider_data');
});

test('a purchase of another center is not found', function (): void {
    $other = ($this->purchase)([], CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$other->id}")
        ->assertNotFound();
});
