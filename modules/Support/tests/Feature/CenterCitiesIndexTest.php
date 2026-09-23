<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Models\City;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('center cities returns empty array when no cities exist', function () {
    $response = $this->getJson('/api/v1/center/cities');

    $response->assertStatus(200);
    expect($response->json())->toBe([]);
});

test('center cities returns all cities regardless of centers', function () {
    $cityWithVisible = CityFactory::new()->create(['name' => ['en' => 'Alpha', 'ar' => 'ألفا']]);
    CenterFactory::new()->for($cityWithVisible)->create(['status' => CenterStatus::VISIBLE]);

    $cityWithInvisible = CityFactory::new()->create(['name' => ['en' => 'Beta', 'ar' => 'بيتا']]);
    CenterFactory::new()->for($cityWithInvisible)->create(['status' => CenterStatus::INVISIBLE]);

    $cityWithNoCenters = CityFactory::new()->create(['name' => ['en' => 'Gamma', 'ar' => 'جاما']]);

    $response = $this->getJson('/api/v1/center/cities');

    $response->assertStatus(200);
    $response->assertJsonCount(3);

    $ids = collect($response->json())->pluck('id')->all();
    expect($ids)->toContain($cityWithVisible->id);
    expect($ids)->toContain($cityWithInvisible->id);
    expect($ids)->toContain($cityWithNoCenters->id);
});

test('center cities is ordered by name ascending', function () {
    $names = ['Zulu', 'Alpha', 'Mike', 'Beta'];
    $cities = collect($names)->map(function ($name) {
        return CityFactory::new()->create(['name' => ['en' => $name, 'ar' => $name]]);
    });

    $response = $this->getJson('/api/v1/center/cities');

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

test('center cities is accessible without authentication', function () {
    CityFactory::new()->create();

    $response = $this->getJson('/api/v1/center/cities');

    $response->assertStatus(200);
    $response->assertJsonCount(1);
});

test('center cities response structure matches city columns', function () {
    CityFactory::new()->create([
        'name' => ['en' => 'Example', 'ar' => 'مثال'],
        'lat' => 12.345678,
        'lng' => 45.678901,
        'radius' => 25,
    ]);

    $response = $this->getJson('/api/v1/center/cities');

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
});
