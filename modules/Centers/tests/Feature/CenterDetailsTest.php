<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\TagFactory;
use Modules\Centers\Models\Center;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Services\Database\Factories\PriceOptionFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
use Modules\Support\Database\Factories\RatingFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createCenterService(Center $center, ActivationStatus $status, array $prices): Service
{
    $service = Resort::query()->create()->service()->create([
        'name' => ['ar' => 'اسم', 'en' => 'Name'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $center->id,
        'status' => $status,
        'type' => ServiceType::Resort,
    ]);

    foreach ($prices as $price) {
        PriceOptionFactory::new()->create(['service_id' => $service->id, 'price' => $price, 'quantity' => 1]);
    }

    return $service;
}

test('returns center details with services overview', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible', 'lat' => 24.7, 'lng' => 46.7]);
    $center->tags()->attach(TagFactory::new()->create()->id);
    $active = createCenterService($center, ActivationStatus::ACTIVE, [30000, 12000]);
    createCenterService($center, ActivationStatus::INACTIVE, [100]);
    PriceOptionFactory::new()->create(['service_id' => $active->id, 'price' => 500, 'quantity' => null]);

    $this->getJson("/api/v1/clients/centers/{$center->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $center->id)
        ->assertJsonPath('data.slug', $center->slug)
        ->assertJsonPath('data.distance_km', null)
        ->assertJsonCount(1, 'data.tags')
        ->assertJsonCount(1, 'data.services')
        ->assertJsonPath('data.services.0.id', $active->id)
        ->assertJsonPath('data.services.0.start_price', 12000)
        ->assertJsonStructure(['data' => [
            'name', 'description', 'images', 'cover', 'logo', 'city', 'location' => ['lat', 'lng'],
            'address', 'slug', 'contact_phone', 'distance_km', 'tags',
            'services' => [['name', 'cover', 'start_price']],
        ]]);
});

test('resort start_price ignores add-ons', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);
    $resort = createCenterService($center, ActivationStatus::ACTIVE, [20000]);
    PriceOptionFactory::new()->create(['service_id' => $resort->id, 'price' => 500, 'quantity' => null]);

    $this->getJson("/api/v1/clients/centers/{$center->slug}")
        ->assertOk()
        ->assertJsonPath('data.services.0.start_price', 20000);
});

test('services show the discounted display_price while an automatic offer runs', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);
    $service = createCenterService($center, ActivationStatus::ACTIVE, [5000]);

    $this->getJson("/api/v1/clients/centers/{$center->slug}")
        ->assertOk()
        ->assertJsonPath('data.services.0.display_price', null);

    PromotionFactory::new()->active()->forCenter($center)->percentage(2000)->create();

    $this->getJson("/api/v1/clients/centers/{$center->slug}")
        ->assertOk()
        ->assertJsonPath('data.services.0.id', $service->id)
        ->assertJsonPath('data.services.0.start_price', 5000)
        ->assertJsonPath('data.services.0.display_price.original', 5000)
        ->assertJsonPath('data.services.0.display_price.discounted', 4000)
        ->assertJsonPath('data.services.0.display_price.discount_amount', 1000);
});

test('returns distance_km when lat and lng are given', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible', 'lat' => 0, 'lng' => 0]);

    $response = $this->getJson("/api/v1/clients/centers/{$center->slug}?lat=0&lng=1")->assertOk();

    expect($response->json('data.distance_km'))->toEqualWithDelta(111.19, 0.1);
});

test('lat without lng fails validation', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);

    $this->getJson("/api/v1/clients/centers/{$center->slug}?lat=10")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lng');
});

test('invisible or unknown centers return 404', function (): void {
    $hidden = CenterFactory::new()->create(['status' => 'invisible']);

    $this->getJson("/api/v1/clients/centers/{$hidden->slug}")->assertNotFound();
    $this->getJson('/api/v1/clients/centers/does-not-exist')->assertNotFound();
});

test('include=ratings adds the summary and the 10 highest ratings', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);
    $client = ClientFactory::new()->create(['first_name' => 'Noura', 'last_name' => 'Ali']);
    ClientFactory::new()->count(10)->create()->each(
        fn ($rater) => RatingFactory::new()->forRateable($center)->byRater($rater)->oneStar()->create(),
    );
    $best = RatingFactory::new()->forRateable($center)->byRater($client)->fiveStars()->create();
    RatingFactory::new()->forRateable(CenterFactory::new()->create())->byRater($client)->fiveStars()->create();

    $this->getJson("/api/v1/clients/centers/{$center->slug}?include=ratings")
        ->assertOk()
        ->assertJsonPath('data.ratings.summary.count', 11)
        ->assertJsonPath('data.ratings.summary.average', 1.36)
        ->assertJsonCount(10, 'data.ratings.data')
        ->assertJsonPath('data.ratings.data.0.id', $best->id)
        ->assertJsonPath('data.ratings.data.0.client', 'Noura Ali')
        ->assertJsonPath('data.ratings.data.0.stars', 5);
});

test('ratings are left out without include', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);
    RatingFactory::new()->forRateable($center)->byRater(ClientFactory::new()->create())->create();

    $this->getJson("/api/v1/clients/centers/{$center->slug}")
        ->assertOk()
        ->assertJsonMissingPath('data.ratings');
});

test('include=ratings returns an empty summary for a center without ratings', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);

    $this->getJson("/api/v1/clients/centers/{$center->slug}?include=ratings")
        ->assertOk()
        ->assertJsonPath('data.ratings.summary', ['average' => null, 'count' => 0])
        ->assertJsonPath('data.ratings.data', []);
});

test('an unknown include fails validation', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);

    $this->getJson("/api/v1/clients/centers/{$center->slug}?include=ratings,owner")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('include.1');
});
