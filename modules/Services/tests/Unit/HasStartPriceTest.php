<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Database\Factories\EventFactory;
use Modules\Services\Database\Factories\ResortFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Event;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $paths = [
        base_path('modules/Support/database/migrations'),
        base_path('modules/Centers/database/migrations'),
        base_path('modules/Services/database/migrations'),
    ];
    foreach ($paths as $path) {
        foreach (glob($path.'/*.php') as $file) {
            require_once $file;
        }
    }
    Artisan::call('migrate', [
        '--path' => $paths,
        '--realpath' => true,
    ]);
});

/**
 * @param  array<int, array{price: float, quantity?: int|null, name?: string|null}>  $priceOptions
 */
function attachPriceOptions(object $serviceable, ServiceType $type, array $priceOptions): Service
{
    $center = CenterFactory::new()->create();

    $service = $serviceable->service()->create([
        'name' => ['ar' => 'اسم'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $center->id,
        'status' => ActivationStatus::ACTIVE,
        'type' => $type,
    ]);

    foreach ($priceOptions as $option) {
        $service->priceOptions()->create([
            'price' => $option['price'],
            'quantity' => $option['quantity'] ?? null,
            'name' => $option['name'] ?? null,
            'unit' => PriceOptionUnit::OPTION,
        ]);
    }

    return $service;
}

// ────────────────────────────────────────────────────────────────────
//  DEFAULT BEHAVIOUR (priceOptions)
// ────────────────────────────────────────────────────────────────────

test('scopeWithStartPrice sets start_price to the min price across priceOptions', function () {
    $event = EventFactory::new()->create();
    attachPriceOptions($event, ServiceType::Event, [
        ['price' => 50],
        ['price' => 25],
        ['price' => 75],
    ]);

    $loaded = Event::withStartPrice()->find($event->id);

    expect($loaded->start_price)->toBe(25.0);
});

test('accessor falls back to the loaded priceOptions relation without extra queries', function () {
    $event = EventFactory::new()->create();
    attachPriceOptions($event, ServiceType::Event, [
        ['price' => 50],
        ['price' => 25],
    ]);

    $loaded = Event::query()->with('priceOptions')->find($event->id);
    expect($loaded->relationLoaded('priceOptions'))->toBeTrue();

    DB::enableQueryLog();
    $startPrice = $loaded->start_price;
    expect(DB::getQueryLog())->toHaveCount(0);

    expect($startPrice)->toBe(25.0);
});

test('accessor falls back to a fresh query when nothing is loaded', function () {
    $event = EventFactory::new()->create();
    attachPriceOptions($event, ServiceType::Event, [
        ['price' => 50],
        ['price' => 25],
    ]);

    $fresh = Event::query()->find($event->id);
    expect($fresh->relationLoaded('priceOptions'))->toBeFalse();

    expect($fresh->start_price)->toBe(25.0);
});

test('start_price is null when there are no price options', function () {
    $event = EventFactory::new()->create();
    attachPriceOptions($event, ServiceType::Event, []);

    expect(Event::withStartPrice()->find($event->id)->start_price)->toBeNull();

    $loaded = Event::query()->with('priceOptions')->find($event->id);
    expect($loaded->start_price)->toBeNull();

    $fresh = Event::query()->find($event->id);
    expect($fresh->start_price)->toBeNull();
});

// ────────────────────────────────────────────────────────────────────
//  RESORT OVERRIDE (dayPrices)
// ────────────────────────────────────────────────────────────────────

test('resort start_price uses dayPrices, ignoring cheaper addon price options', function () {
    $resort = ResortFactory::new()->create();
    attachPriceOptions($resort, ServiceType::Resort, [
        ['price' => 150, 'quantity' => 0],
        ['price' => 220, 'quantity' => 5],
        ['price' => 10, 'name' => 'Addon'],
    ]);

    $loaded = Resort::withStartPrice()->find($resort->id);
    expect($loaded->start_price)->toBe(150.0);

    $fresh = Resort::query()->find($resort->id);
    expect($fresh->relationLoaded('dayPrices'))->toBeFalse();
    expect($fresh->start_price)->toBe(150.0);
});
