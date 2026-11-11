<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();

    $this->purchase = fn ($client = null) => PurchaseFactory::new()->create([
        'buyer_type' => ($client ?? $this->client)->getMorphClass(),
        'buyer_id' => ($client ?? $this->client)->id,
        'merchant_type' => $this->center->getMorphClass(),
        'merchant_id' => $this->center->id,
    ]);
});

test('a new purchase gets a unique 6-character code', function (): void {
    $codes = collect(range(1, 5))->map(fn () => ($this->purchase)()->code);

    expect($codes->unique())->toHaveCount(5)
        ->each->toMatch('/^[A-HJKMNP-Z2-9]{6}$/');
});

test('the purchase lookup requires a client token', function (): void {
    $this->getJson('/api/v1/clients/purchases/code/ABCDEF')->assertUnauthorized();
});

test('shows my purchase by its code, ignoring case', function (): void {
    $purchase = ($this->purchase)();
    PurchaseItemFactory::new()->create(['purchase_id' => $purchase->id]);

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/purchases/code/'.strtolower($purchase->code))
        ->assertOk()
        ->assertJsonPath('data.id', $purchase->id)
        ->assertJsonPath('data.code', $purchase->code)
        ->assertJsonPath('data.center.id', $this->center->id)
        ->assertJsonCount(1, 'data.items');
});

test('another client\'s purchase or an unknown code is not found', function (): void {
    $other = ($this->purchase)(ClientFactory::new()->create());

    $this->actingAs($this->client, 'client')
        ->getJson("/api/v1/clients/purchases/code/{$other->code}")
        ->assertNotFound();

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/purchases/code/ZZZZZZ')
        ->assertNotFound();
});
