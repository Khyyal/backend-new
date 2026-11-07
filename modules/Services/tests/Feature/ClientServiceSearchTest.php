<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Enums\ServiceType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->url = '/api/v1/clients/services';
    $this->center = CenterFactory::new()->create(['status' => 'visible']);
});

test('search is public and returns active services of visible centers across types', function (): void {
    ServiceFactory::new()->create(['center_id' => $this->center->id, 'name' => ['en' => 'Jumping', 'ar' => 'قفز']]);
    ServiceFactory::new()->create(['center_id' => $this->center->id, 'type' => ServiceType::Visit]);
    ServiceFactory::new()->inactive()->create(['center_id' => $this->center->id]);
    ServiceFactory::new()->create(['center_id' => CenterFactory::new()->pending()->create(['status' => 'visible'])->id]);
    ServiceFactory::new()->create(['center_id' => CenterFactory::new()->create(['status' => 'invisible'])->id]);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'type', 'center' => ['id', 'name', 'slug']]], 'meta']);
});

test('filters by text in either language, type, center and city', function (): void {
    ServiceFactory::new()->create(['center_id' => $this->center->id, 'name' => ['en' => 'Jumping', 'ar' => 'قفز']]);
    ServiceFactory::new()->create(['center_id' => $this->center->id, 'name' => ['en' => 'Dressage', 'ar' => 'ترويض'], 'type' => ServiceType::Visit]);
    $other = CenterFactory::new()->create(['status' => 'visible']);
    ServiceFactory::new()->create(['center_id' => $other->id, 'name' => ['en' => 'Jumping 2', 'ar' => 'قفز ٢']]);

    $this->getJson("{$this->url}?search=jump")->assertOk()->assertJsonCount(2, 'data');
    $this->getJson($this->url.'?search='.urlencode('ترويض'))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}?types[]=visit")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'visit');
    $this->getJson("{$this->url}?center_id={$other->id}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}?city_id={$other->city_id}")->assertOk()->assertJsonCount($other->city_id === $this->center->city_id ? 3 : 1, 'data');
    $this->getJson("{$this->url}?types[]=nope")->assertUnprocessable();
});
