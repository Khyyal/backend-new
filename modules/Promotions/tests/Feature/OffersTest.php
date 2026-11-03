<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->client = ClientFactory::new()->create();
    $this->center = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);
});

test('lists running offers and hides drafts and used up offers', function (): void {
    $running = PromotionFactory::new()->active()->coupon()->create();
    PromotionFactory::new()->create();
    PromotionFactory::new()->active()->create(['total_usage_limit' => 1, 'usage_count' => 1]);

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/offers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $running->id)
        ->assertJsonPath('data.0.requires_coupon', true)
        ->assertJsonMissingPath('data.0.usage_count');
});

test('shows an offer with the services it covers and their discounted price', function (): void {
    $promotion = PromotionFactory::new()->active()->forCenter($this->center)->percentage(2000)->create();
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id, 'status' => ActivationStatus::ACTIVE]);
    $service->priceOptions()->create(['price' => 5000]);
    ServiceFactory::new()->create(['status' => ActivationStatus::ACTIVE]);

    $this->actingAs($this->client, 'client')
        ->getJson("/api/v1/clients/offers/{$promotion->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.services')
        ->assertJsonPath('data.services.0.id', $service->id)
        ->assertJsonPath('data.services.0.display_price.discounted', 4000);
});

test('returns 404 for an offer that is not running', function (): void {
    $draft = PromotionFactory::new()->create();

    $this->actingAs($this->client, 'client')
        ->getJson("/api/v1/clients/offers/{$draft->id}")
        ->assertNotFound();
});
