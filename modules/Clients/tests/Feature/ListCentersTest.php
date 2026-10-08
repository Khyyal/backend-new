<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\TagFactory;
use Modules\Centers\Models\Center;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\City;
use Modules\Support\Services\RatingService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    $this->url = '/api/v1/clients/centers';
});

test('only visible centers are returned', function (): void {
    $visible = CenterFactory::new()->create(['status' => 'visible']);
    CenterFactory::new()->create(['status' => 'invisible']);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id);
});

test('filters by city_id', function (): void {
    $city = City::factory()->create();
    $match = CenterFactory::new()->create(['status' => 'visible', 'city_id' => $city->id]);
    CenterFactory::new()->create(['status' => 'visible']);

    $this->getJson("{$this->url}?city_id={$city->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('filters by search on name', function (): void {
    $match = CenterFactory::new()->create(['status' => 'visible', 'name' => 'Golden Stables']);
    CenterFactory::new()->create(['status' => 'visible', 'name' => 'Other Place']);

    $this->getJson("{$this->url}?search=golden")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('filters by services_types matching any given type', function (): void {
    $match = CenterFactory::new()->create(['status' => 'visible']);
    $other = CenterFactory::new()->create(['status' => 'visible']);

    attachCenterServiceOfType($match, ServiceType::Resort);
    attachCenterServiceOfType($other, ServiceType::Event);

    $this->getJson("{$this->url}?services_types[]=resort")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('filters by tags matching any given tag', function (): void {
    $tag = TagFactory::new()->create();
    $match = CenterFactory::new()->create(['status' => 'visible']);
    $match->tags()->attach($tag->id);
    CenterFactory::new()->create(['status' => 'visible']);

    $this->getJson("{$this->url}?tags[]={$tag->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('filters by bounds', function (): void {
    $inside = CenterFactory::new()->create(['status' => 'visible', 'lat' => 10, 'lng' => 10]);
    CenterFactory::new()->create(['status' => 'visible', 'lat' => 50, 'lng' => 50]);

    $this->getJson("{$this->url}?".http_build_query([
        'bounds' => ['north' => 20, 'south' => 0, 'east' => 20, 'west' => 0],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inside->id);
});

test('bounds requires all four corners together', function (): void {
    $this->getJson("{$this->url}?".http_build_query(['bounds' => ['north' => 20]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bounds.south', 'bounds.east', 'bounds.west']);
});

test('orders by most_popular (points) by default', function (): void {
    $low = CenterFactory::new()->create(['status' => 'visible', 'points' => 1]);
    $high = CenterFactory::new()->create(['status' => 'visible', 'points' => 10]);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.id', $high->id)
        ->assertJsonPath('data.1.id', $low->id);
});

test('orders by nearest distance when requested', function (): void {
    $far = CenterFactory::new()->create(['status' => 'visible', 'lat' => 10, 'lng' => 10]);
    $near = CenterFactory::new()->create(['status' => 'visible', 'lat' => 0.1, 'lng' => 0.1]);

    $this->getJson("{$this->url}?order=nearest&lat=0&lng=0")
        ->assertOk()
        ->assertJsonPath('data.0.id', $near->id)
        ->assertJsonPath('data.1.id', $far->id);
});

test('nearest order requires lat and lng', function (): void {
    $this->getJson("{$this->url}?order=nearest")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lat', 'lng']);
});

test('returns rating average and count', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible']);
    $clientA = ClientFactory::new()->create();
    $clientB = ClientFactory::new()->create();

    $ratingService = app(RatingService::class);
    $ratingService->create($clientA, $center, 5, null);
    $ratingService->create($clientB, $center, 3, null);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.rating.avg', 4)
        ->assertJsonPath('data.0.rating.count', 2);
});

test('returns null rating average when there are no ratings', function (): void {
    CenterFactory::new()->create(['status' => 'visible']);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.rating.avg', null)
        ->assertJsonPath('data.0.rating.count', 0);
});

test('returns logo and cover thumb urls', function (): void {
    Storage::fake('public');
    $center = CenterFactory::new()->create(['status' => 'visible']);
    $center->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo');
    $center->addMedia(UploadedFile::fake()->image('cover.png'))->toMediaCollection('cover');

    $response = $this->getJson($this->url)->assertOk();

    expect($response->json('data.0.logo'))->toContain('thumb');
    expect($response->json('data.0.cover'))->toContain('cover-thumb');
});

test('response is cached: a change after the first call is not reflected within the TTL', function (): void {
    $center = CenterFactory::new()->create(['status' => 'visible', 'points' => 1]);

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');

    CenterFactory::new()->create(['status' => 'visible', 'points' => 2]);

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');

    Cache::flush();

    $this->getJson($this->url)->assertOk()->assertJsonCount(2, 'data');
});

function attachCenterServiceOfType(Center $center, ServiceType $type): void
{
    Resort::query()->create()->service()->create([
        'name' => ['ar' => 'اسم'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $center->id,
        'status' => ActivationStatus::ACTIVE,
        'type' => $type,
    ]);
}
