<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\HorseService;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Services\HorseServiceService;
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
        'name' => ['ar' => 'خدمة الخيول', 'en' => 'Horse Service'],
        'description' => ['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description'],
        'price_options' => [
            ['name' => 'Basic', 'price' => 100],
            ['name' => 'Premium', 'price' => 200],
        ],
    ];
};

// ────────────────────────────────────────────────────────────────────
//  CREATE
// ────────────────────────────────────────────────────────────────────

test('create builds full graph: counts increment for all 3 entities', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);

    $before = [
        'horseService' => HorseService::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $horseService = $svc->create($validData($center->id));

    expect(HorseService::withTrashed()->count())->toBe($before['horseService'] + 1);
    expect(Service::withTrashed()->count())->toBe($before['service'] + 1);
    expect(PriceOption::count())->toBe($before['option'] + 2);
    expect($horseService)->toBeInstanceOf(HorseService::class);
});

test('create: Service row has correct enum/center metadata', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));

    $service = $horseService->service;
    expect($service->type)->toBe(ServiceType::HorseService);
    expect($service->status)->toBe(ActivationStatus::ACTIVE);
    expect($service->center_id)->toBe($center->id);
    expect($service->slug)->not->toBeEmpty();
    expect($service->serviceable_type)->toBe(HorseService::class);
    expect($service->serviceable_id)->toBe($horseService->id);
});

test('create: morphOne Service ↔ HorseService bidirectional link', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));

    $serviceFromRelation = $horseService->service;
    $serviceable = $serviceFromRelation->serviceable;
    expect($serviceable->is($horseService))->toBeTrue();
});

test('create: translatable JSON (ar required, en optional)', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);

    $horseService = $svc->create($validData($center->id));
    expect($horseService->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'خدمة الخيول', 'en' => 'Horse Service']);
    expect($horseService->service->getTranslations('description'))
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

test('create: PriceOptions correct column mapping (name+price, no quantity) + count', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));

    $options = $horseService->service->priceOptions()->orderBy('price')->get();
    expect($options)->toHaveCount(2);
    expect($options[0]->name)->toBe('Basic');
    expect($options[0]->price)->toBe(100.0);
    expect($options[0]->quantity)->toBeNull();
    expect($options[0]->unit)->toBe(PriceOptionUnit::OPTION);
    expect($options[1]->name)->toBe('Premium');
    expect($options[1]->price)->toBe(200.0);
});

test('create: single price option is allowed', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $data = array_replace($validData($center->id), ['price_options' => [['name' => 'Only', 'price' => 50]]]);
    $horseService = $svc->create($data);

    expect($horseService->service->priceOptions)->toHaveCount(1);
});

test('create: returned model eager-loads service and service.priceOptions relations', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));

    expect($horseService->relationLoaded('service'))->toBeTrue();
    expect($horseService->service->relationLoaded('priceOptions'))->toBeTrue();

    DB::enableQueryLog();
    $horseService->service->getAttribute('service');
    $horseService->service->getRelation('priceOptions');
    expect(DB::getQueryLog())->toHaveCount(0);
});

// ────────────────────────────────────────────────────────────────────
//  UPDATE
// ────────────────────────────────────────────────────────────────────

test('update: only translations change; slug/center_id/type/status preserved', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));
    $old = $horseService->service->replicate();

    $newData = array_replace($validData($center->id), [
        'name' => ['ar' => 'جديد', 'en' => 'Updated'],
        'description' => ['ar' => 'وصف جديد', 'en' => 'New description'],
    ]);
    $updated = $svc->update($horseService, $newData);

    expect($updated->service->slug)->toBe($old->slug);
    expect($updated->service->center_id)->toBe($old->center_id);
    expect($updated->service->type)->toBe($old->type);
    expect($updated->service->status)->toBe($old->status);
    expect($updated->service->serviceable_type)->toBe($old->serviceable_type);
    expect($updated->service->serviceable_id)->toBe($old->serviceable_id);
    expect($updated->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'جديد', 'en' => 'Updated']);
});

test('update: PriceOptions full sync delete-then-recreate no stale IDs', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));
    $oldIds = $horseService->service->priceOptions()->pluck('id')->all();
    expect($oldIds)->toHaveCount(2);

    $newOpts = [
        ['name' => 'VIP', 'price' => 300],
        ['name' => 'Standard', 'price' => 150],
        ['name' => 'Student', 'price' => 80],
    ];
    $newData = array_replace($validData($center->id), ['price_options' => $newOpts]);
    $updated = $svc->update($horseService, $newData);

    expect(PriceOption::query()->whereIn('id', $oldIds)->count())->toBe(0);
    $opts = $updated->service->priceOptions()->orderBy('price')->get();
    expect($opts)->toHaveCount(3);
    expect($opts->pluck('name')->all())->toBe(['Student', 'Standard', 'VIP']);
});

test('update: returned model is fresh with relations preloaded', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));

    $newData = array_replace($validData($center->id), [
        'price_options' => [['name' => 'Single', 'price' => 20]],
    ]);
    $updated = $svc->update($horseService, $newData);

    expect($updated->relationLoaded('service'))->toBeTrue();
    expect($updated->service->relationLoaded('priceOptions'))->toBeTrue();
    expect($updated->service->priceOptions)->toHaveCount(1);
    expect($updated->wasRecentlyCreated)->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────
//  DELETE
// ────────────────────────────────────────────────────────────────────

test('delete: priceOptions removed; service and horseService soft-deleted', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));

    $serviceId = $horseService->service->id;
    $horseServiceId = $horseService->id;

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(HorseService::query()->find($horseServiceId))->not->toBeNull();
    expect(PriceOption::query()->count())->toBe(2);

    $svc->delete($horseService);

    expect(PriceOption::query()->count())->toBe(0);

    expect(Service::query()->find($serviceId))->toBeNull();
    expect(HorseService::query()->find($horseServiceId))->toBeNull();
    expect(Service::withTrashed()->find($serviceId))->not->toBeNull();
    expect(HorseService::withTrashed()->find($horseServiceId))->not->toBeNull();
});

test('delete: returns void', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));

    $result = $svc->delete($horseService);
    expect($result)->toBeNull();
});

// ────────────────────────────────────────────────────────────────────
//  TRANSACTIONS (rollback)
// ────────────────────────────────────────────────────────────────────

test('create mid-flow failure rolls back all 3 tables', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $before = [
        'horseService' => HorseService::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $failingSvc = new class extends HorseServiceService
    {
        public function create(array $data): HorseService
        {
            return DB::transaction(function () use ($data) {
                $horseService = HorseService::query()->create();
                $horseService->service()->create([
                    'name' => ['ar' => $data['name']['ar']],
                    'description' => ['ar' => $data['description']['ar']],
                    'center_id' => $data['center_id'],
                    'status' => ActivationStatus::ACTIVE,
                    'type' => ServiceType::HorseService,
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

    expect(HorseService::withTrashed()->count())->toBe($before['horseService']);
    expect(Service::withTrashed()->count())->toBe($before['service']);
    expect(PriceOption::count())->toBe($before['option']);
});

test('delete mid-flow failure rolls back all deletions', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseServiceService::class);
    $horseService = $svc->create($validData($center->id));
    $serviceId = $horseService->service->id;
    $horseServiceId = $horseService->id;
    $oldOptionIds = $horseService->service->priceOptions()->pluck('id')->all();

    $failingDeleter = new class extends HorseServiceService
    {
        public function delete(HorseService $horseService): void
        {
            DB::transaction(function () use ($horseService) {
                $service = $horseService->service()->lockForUpdate()->firstOrFail();
                $service->priceOptions()->delete();
                throw new RuntimeException('intentional mid-delete fail');
            });
        }
    };

    try {
        $failingDeleter->delete($horseService);
        $this->fail('expected exception not thrown');
    } catch (RuntimeException $e) {
    }

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(HorseService::query()->find($horseServiceId))->not->toBeNull();
    expect(PriceOption::query()->whereIn('id', $oldOptionIds)->count())->toBe(2);
});
