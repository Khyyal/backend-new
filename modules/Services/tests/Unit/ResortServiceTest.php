<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
use Modules\Services\Services\ResortService;
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

$validData = function (int $centerId): array {
    return [
        'center_id' => $centerId,
        'name' => ['ar' => 'منتجع', 'en' => 'Resort'],
        'description' => ['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description'],
        'days' => [
            ['day' => 0, 'price' => 150],
            ['day' => 5, 'price' => 220],
        ],
        'price_options' => [
            ['name' => 'Adult', 'price' => 50],
            ['name' => 'Child', 'price' => 25],
        ],
    ];
};

// ────────────────────────────────────────────────────────────────────
//  CREATE
// ────────────────────────────────────────────────────────────────────

test('create builds full graph: counts increment for all 3 entities', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);

    $before = [
        'resort' => Resort::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $resort = $svc->create($validData($center->id));

    expect(Resort::withTrashed()->count())->toBe($before['resort'] + 1);
    expect(Service::withTrashed()->count())->toBe($before['service'] + 1);
    expect(PriceOption::count())->toBe($before['option'] + 4); // 2 days + 2 price options
    expect($resort)->toBeInstanceOf(Resort::class);
});

test('create: Service row has correct enum/center metadata', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    $service = $resort->service;
    expect($service->type)->toBe(ServiceType::Resort);
    expect($service->status)->toBe(ActivationStatus::ACTIVE);
    expect($service->center_id)->toBe($center->id);
    expect($service->slug)->not->toBeEmpty();
    expect($service->serviceable_type)->toBe(Resort::class);
    expect($service->serviceable_id)->toBe($resort->id);
});

test('create: morphOne Service ↔ Resort bidirectional link', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    $serviceFromRelation = $resort->service;
    $serviceable = $serviceFromRelation->serviceable;
    expect($serviceable->is($resort))->toBeTrue();
});

test('create: translatable JSON (ar required, en optional)', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);

    $resort = $svc->create($validData($center->id));
    expect($resort->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'منتجع', 'en' => 'Resort']);
    expect($resort->service->getTranslations('description'))
        ->toMatchArray(['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description']);

    $arOnly = array_replace($validData($center->id), [
        'name' => ['ar' => 'فقط عربي'],
        'description' => ['ar' => 'وصف عربي فقط'],
    ]);
    $r2 = $svc->create($arOnly);
    $name = $r2->service->getTranslations('name');
    $desc = $r2->service->getTranslations('description');
    expect($name)->toHaveKey('ar', 'فقط عربي');
    expect($name)->not->toHaveKey('en');
    expect($desc)->toHaveKey('ar', 'وصف عربي فقط');
    expect($desc)->not->toHaveKey('en');
});

test('create: addon PriceOptions have name + null quantity', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    $options = $resort->service->priceOptions()->whereNull('quantity')->orderBy('price')->get();
    expect($options)->toHaveCount(2);
    expect($options[0]->name)->toBe('Child');
    expect($options[0]->price)->toBe(25.0);
    expect($options[0]->quantity)->toBeNull();
    expect($options[0]->unit)->toBe(PriceOptionUnit::OPTION);
    expect($options[1]->name)->toBe('Adult');
    expect($options[1]->price)->toBe(50.0);
});

test('create: day-price PriceOptions carry the weekday in quantity, no name', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    $dayPrices = $resort->service->priceOptions()->whereNotNull('quantity')->orderBy('quantity')->get();
    expect($dayPrices)->toHaveCount(2);
    expect($dayPrices[0]->quantity)->toBe(0);
    expect($dayPrices[0]->price)->toBe(150.0);
    expect($dayPrices[0]->name)->toBeNull();
    expect($dayPrices[0]->unit)->toBe(PriceOptionUnit::OPTION);
    expect($dayPrices[1]->quantity)->toBe(5);
    expect($dayPrices[1]->price)->toBe(220.0);
});

test('create: partial weekday coverage is allowed', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $data = array_replace($validData($center->id), ['days' => [['day' => 2, 'price' => 99]]]);
    $resort = $svc->create($data);

    $dayPrices = $resort->service->priceOptions()->whereNotNull('quantity')->get();
    expect($dayPrices)->toHaveCount(1);
    expect($dayPrices->first()->quantity)->toBe(2);
});

test('create: returned model eager-loads service and service.priceOptions relations', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    expect($resort->relationLoaded('service'))->toBeTrue();
    expect($resort->service->relationLoaded('priceOptions'))->toBeTrue();

    DB::enableQueryLog();
    $resort->service->getAttribute('service');
    $resort->service->getRelation('priceOptions');
    expect(DB::getQueryLog())->toHaveCount(0);
});

// ────────────────────────────────────────────────────────────────────
//  UPDATE
// ────────────────────────────────────────────────────────────────────

test('update: only translations change; slug/center_id/type/status preserved', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));
    $old = $resort->service->replicate();

    $newData = array_replace($validData($center->id), [
        'name' => ['ar' => 'جديد', 'en' => 'Updated'],
        'description' => ['ar' => 'وصف جديد', 'en' => 'New description'],
    ]);
    $updated = $svc->update($resort, $newData);

    expect($updated->service->slug)->toBe($old->slug);
    expect($updated->service->center_id)->toBe($old->center_id);
    expect($updated->service->type)->toBe($old->type);
    expect($updated->service->status)->toBe($old->status);
    expect($updated->service->serviceable_type)->toBe($old->serviceable_type);
    expect($updated->service->serviceable_id)->toBe($old->serviceable_id);
    expect($updated->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'جديد', 'en' => 'Updated']);
});

test('update: PriceOptions full sync delete-then-recreate no stale IDs (both kinds)', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));
    $oldIds = $resort->service->priceOptions()->pluck('id')->all();
    expect($oldIds)->toHaveCount(4);

    $newData = array_replace($validData($center->id), [
        'days' => [['day' => 3, 'price' => 300]],
        'price_options' => [
            ['name' => 'VIP', 'price' => 200],
            ['name' => 'Standard', 'price' => 80],
            ['name' => 'Student', 'price' => 40],
        ],
    ]);
    $updated = $svc->update($resort, $newData);

    expect(PriceOption::query()->whereIn('id', $oldIds)->count())->toBe(0);

    $addons = $updated->service->priceOptions()->whereNull('quantity')->orderBy('price')->get();
    expect($addons)->toHaveCount(3);
    expect($addons->pluck('name')->all())->toBe(['Student', 'Standard', 'VIP']);

    $days = $updated->service->priceOptions()->whereNotNull('quantity')->get();
    expect($days)->toHaveCount(1);
    expect($days->first()->quantity)->toBe(3);
    expect($days->first()->price)->toBe(300.0);
});

test('update: returned model is fresh with relations preloaded', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    $newData = array_replace($validData($center->id), [
        'price_options' => [['name' => 'Single', 'price' => 20]],
        'days' => [['day' => 1, 'price' => 10]],
    ]);
    $updated = $svc->update($resort, $newData);

    expect($updated->relationLoaded('service'))->toBeTrue();
    expect($updated->service->relationLoaded('priceOptions'))->toBeTrue();
    expect($updated->service->priceOptions)->toHaveCount(2);
    expect($updated->wasRecentlyCreated)->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────
//  DELETE
// ────────────────────────────────────────────────────────────────────

test('delete: priceOptions removed; service and resort soft-deleted', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    $serviceId = $resort->service->id;
    $resortId = $resort->id;

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(Resort::query()->find($resortId))->not->toBeNull();
    expect(PriceOption::query()->count())->toBe(4);

    $svc->delete($resort);

    expect(PriceOption::query()->count())->toBe(0);

    expect(Service::query()->find($serviceId))->toBeNull();
    expect(Resort::query()->find($resortId))->toBeNull();
    expect(Service::withTrashed()->find($serviceId))->not->toBeNull();
    expect(Resort::withTrashed()->find($resortId))->not->toBeNull();
});

test('delete: returns void', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));

    $result = $svc->delete($resort);
    expect($result)->toBeNull();
});

// ────────────────────────────────────────────────────────────────────
//  TRANSACTIONS (rollback)
// ────────────────────────────────────────────────────────────────────

test('create mid-flow failure rolls back all 3 tables', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $before = [
        'resort' => Resort::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $failingSvc = new class extends ResortService
    {
        public function create(array $data): Resort
        {
            return DB::transaction(function () use ($data) {
                $resort = Resort::query()->create();
                $resort->service()->create([
                    'name' => ['ar' => $data['name']['ar']],
                    'description' => ['ar' => $data['description']['ar']],
                    'center_id' => $data['center_id'],
                    'status' => ActivationStatus::ACTIVE,
                    'type' => ServiceType::Resort,
                ]);
                throw new RuntimeException('intentional mid-create fail');
            });
        }
    };

    try {
        $failingSvc->create($validData($center->id));
        $this->fail('expected exception not thrown');
    } catch (RuntimeException $e) {
    }

    expect(Resort::withTrashed()->count())->toBe($before['resort']);
    expect(Service::withTrashed()->count())->toBe($before['service']);
    expect(PriceOption::count())->toBe($before['option']);
});

test('delete mid-flow failure rolls back all deletions', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(ResortService::class);
    $resort = $svc->create($validData($center->id));
    $serviceId = $resort->service->id;
    $resortId = $resort->id;
    $oldOptionIds = $resort->service->priceOptions()->pluck('id')->all();

    $failingDeleter = new class extends ResortService
    {
        public function delete(Resort $resort): void
        {
            DB::transaction(function () use ($resort) {
                $service = $resort->service()->lockForUpdate()->firstOrFail();
                $service->priceOptions()->delete();
                throw new RuntimeException('intentional mid-delete fail');
            });
        }
    };

    try {
        $failingDeleter->delete($resort);
        $this->fail('expected exception not thrown');
    } catch (RuntimeException $e) {
    }

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(Resort::query()->find($resortId))->not->toBeNull();
    expect(PriceOption::query()->whereIn('id', $oldOptionIds)->count())->toBe(4);
});
