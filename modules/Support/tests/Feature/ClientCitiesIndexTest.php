<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Models\City;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('client cities returns empty array when no cities exist', function () {
    $response = $this->getJson('/api/v1/client/cities');

    $response->assertStatus(200);
    expect($response->json())->toBe([]);
});

test('client cities includes only cities with at least one visible center', function () {
    $cityWithVisible = CityFactory::new()->create(['name' => ['en' => 'Alpha', 'ar' => 'ألفا']]);
    CenterFactory::new()->for($cityWithVisible)->create(['status' => CenterStatus::VISIBLE]);

    $cityWithInvisible = CityFactory::new()->create(['name' => ['en' => 'Beta', 'ar' => 'بيتا']]);
    CenterFactory::new()->for($cityWithInvisible)->create(['status' => CenterStatus::INVISIBLE]);

    $cityWithMixed = CityFactory::new()->create(['name' => ['en' => 'Gamma', 'ar' => 'جاما']]);
    CenterFactory::new()->for($cityWithMixed)->create(['status' => CenterStatus::INVISIBLE]);
    CenterFactory::new()->for($cityWithMixed)->create(['status' => CenterStatus::VISIBLE]);

    $cityWithNoCenters = CityFactory::new()->create(['name' => ['en' => 'Delta', 'ar' => 'دلتا']]);

    $response = $this->getJson('/api/v1/client/cities');

    $response->assertStatus(200);

    $ids = collect($response->json())->pluck('id')->all();

    expect($ids)->toContain($cityWithVisible->id);
    expect($ids)->toContain($cityWithMixed->id);
    expect($ids)->not->toContain($cityWithInvisible->id);
    expect($ids)->not->toContain($cityWithNoCenters->id);
});

test('client cities is ordered by name ascending', function () {
    $names = ['Zulu', 'Alpha', 'Mike', 'Beta'];
    $cities = collect($names)->map(function ($name) {
        $city = CityFactory::new()->create(['name' => ['en' => $name, 'ar' => $name]]);
        CenterFactory::new()->for($city)->create(['status' => CenterStatus::VISIBLE]);

        return $city;
    });

    $response = $this->getJson('/api/v1/client/cities');

    $response->assertStatus(200);

    $returnedNames = collect($response->json())
        ->map(fn (array $city) => $city['name']['en'] ?? null)
        ->values()
        ->all();
    $expectedNames = $cities->sortBy(fn (City $c) => $c->getTranslation('name', 'en'))
        ->map(fn (City $c) => $c->getTranslation('name', 'en'))
        ->values()
        ->all();

    expect($returnedNames)->toBe($expectedNames);
});

test('client cities is accessible without authentication', function () {
    $city = CityFactory::new()->create();
    CenterFactory::new()->for($city)->create(['status' => CenterStatus::VISIBLE]);

    $response = $this->getJson('/api/v1/client/cities');

    $response->assertStatus(200);
    $response->assertJsonCount(1);
});

test('client cities response structure matches city columns', function () {
    $city = CityFactory::new()->create([
        'name' => ['en' => 'Example', 'ar' => 'مثال'],
        'lat' => 12.345678,
        'lng' => 45.678901,
        'radius' => 25,
    ]);
    CenterFactory::new()->for($city)->create(['status' => CenterStatus::VISIBLE]);

    $response = $this->getJson('/api/v1/client/cities');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'lat',
                'lng',
                'radius',
                'created_at',
                'updated_at',
            ],
        ]);

    $item = $response->json(0);
    expect($item['name'])->toBeArray();
    expect($item['name'])->toHaveKeys(['en', 'ar']);
    expect($item['name']['en'])->toBe('Example');
    expect($item['name']['ar'])->toBe('مثال');
    expect($item['lat'])->toBe(12.345678);
    expect($item['lng'])->toBe(45.678901);
    expect($item['radius'])->toBe(25);
});
