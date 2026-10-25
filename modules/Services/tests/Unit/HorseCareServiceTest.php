<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\HorseCare;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Services\HorseCareService;
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
        'name' => ['ar' => 'رعاية الخيول', 'en' => 'Horse Care'],
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
    $svc = app(HorseCareService::class);

    $before = [
        'horseCare' => HorseCare::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $horseCare = $svc->create($validData($center->id));

    expect(HorseCare::withTrashed()->count())->toBe($before['horseCare'] + 1);
    expect(Service::withTrashed()->count())->toBe($before['service'] + 1);
    expect(PriceOption::count())->toBe($before['option'] + 2);
    expect($horseCare)->toBeInstanceOf(HorseCare::class);
});

test('create: Service row has correct enum/center metadata', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));

    $service = $horseCare->service;
    expect($service->type)->toBe(ServiceType::HorseCare);
    expect($service->status)->toBe(ActivationStatus::ACTIVE);
    expect($service->center_id)->toBe($center->id);
    expect($service->slug)->not->toBeEmpty();
    expect($service->serviceable_type)->toBe(HorseCare::class);
    expect($service->serviceable_id)->toBe($horseCare->id);
});

test('create: morphOne Service ↔ HorseCare bidirectional link', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));

    $serviceFromRelation = $horseCare->service;
    $serviceable = $serviceFromRelation->serviceable;
    expect($serviceable->is($horseCare))->toBeTrue();
});

test('create: translatable JSON (ar required, en optional)', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);

    $horseCare = $svc->create($validData($center->id));
    expect($horseCare->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'رعاية الخيول', 'en' => 'Horse Care']);
    expect($horseCare->service->getTranslations('description'))
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
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));

    $options = $horseCare->service->priceOptions()->orderBy('price')->get();
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
    $svc = app(HorseCareService::class);
    $data = array_replace($validData($center->id), ['price_options' => [['name' => 'Only', 'price' => 50]]]);
    $horseCare = $svc->create($data);

    expect($horseCare->service->priceOptions)->toHaveCount(1);
});

test('create: returned model eager-loads service and service.priceOptions relations', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));

    expect($horseCare->relationLoaded('service'))->toBeTrue();
    expect($horseCare->service->relationLoaded('priceOptions'))->toBeTrue();

    DB::enableQueryLog();
    $horseCare->service->getAttribute('service');
    $horseCare->service->getRelation('priceOptions');
    expect(DB::getQueryLog())->toHaveCount(0);
});

// ────────────────────────────────────────────────────────────────────
//  UPDATE
// ────────────────────────────────────────────────────────────────────

test('update: only translations change; slug/center_id/type/status preserved', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));
    $old = $horseCare->service->replicate();

    $newData = array_replace($validData($center->id), [
        'name' => ['ar' => 'جديد', 'en' => 'Updated'],
        'description' => ['ar' => 'وصف جديد', 'en' => 'New description'],
    ]);
    $updated = $svc->update($horseCare, $newData);

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
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));
    $oldIds = $horseCare->service->priceOptions()->pluck('id')->all();
    expect($oldIds)->toHaveCount(2);

    $newOpts = [
        ['name' => 'VIP', 'price' => 300],
        ['name' => 'Standard', 'price' => 150],
        ['name' => 'Student', 'price' => 80],
    ];
    $newData = array_replace($validData($center->id), ['price_options' => $newOpts]);
    $updated = $svc->update($horseCare, $newData);

    expect(PriceOption::query()->whereIn('id', $oldIds)->count())->toBe(0);
    $opts = $updated->service->priceOptions()->orderBy('price')->get();
    expect($opts)->toHaveCount(3);
    expect($opts->pluck('name')->all())->toBe(['Student', 'Standard', 'VIP']);
});

test('update: returned model is fresh with relations preloaded', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));

    $newData = array_replace($validData($center->id), [
        'price_options' => [['name' => 'Single', 'price' => 20]],
    ]);
    $updated = $svc->update($horseCare, $newData);

    expect($updated->relationLoaded('service'))->toBeTrue();
    expect($updated->service->relationLoaded('priceOptions'))->toBeTrue();
    expect($updated->service->priceOptions)->toHaveCount(1);
    expect($updated->wasRecentlyCreated)->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────
//  DELETE
// ────────────────────────────────────────────────────────────────────

test('delete: priceOptions removed; service and horseCare soft-deleted', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));

    $serviceId = $horseCare->service->id;
    $horseCareId = $horseCare->id;

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(HorseCare::query()->find($horseCareId))->not->toBeNull();
    expect(PriceOption::query()->count())->toBe(2);

    $svc->delete($horseCare);

    expect(PriceOption::query()->count())->toBe(0);

    expect(Service::query()->find($serviceId))->toBeNull();
    expect(HorseCare::query()->find($horseCareId))->toBeNull();
    expect(Service::withTrashed()->find($serviceId))->not->toBeNull();
    expect(HorseCare::withTrashed()->find($horseCareId))->not->toBeNull();
});

test('delete: returns void', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));

    $result = $svc->delete($horseCare);
    expect($result)->toBeNull();
});

// ────────────────────────────────────────────────────────────────────
//  TRANSACTIONS (rollback)
// ────────────────────────────────────────────────────────────────────

test('create mid-flow failure rolls back all 3 tables', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $before = [
        'horseCare' => HorseCare::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $failingSvc = new class extends HorseCareService
    {
        public function create(array $data): HorseCare
        {
            return DB::transaction(function () use ($data) {
                $horseCare = HorseCare::query()->create();
                $horseCare->service()->create([
                    'name' => ['ar' => $data['name']['ar']],
                    'description' => ['ar' => $data['description']['ar']],
                    'center_id' => $data['center_id'],
                    'status' => ActivationStatus::ACTIVE,
                    'type' => ServiceType::HorseCare,
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

    expect(HorseCare::withTrashed()->count())->toBe($before['horseCare']);
    expect(Service::withTrashed()->count())->toBe($before['service']);
    expect(PriceOption::count())->toBe($before['option']);
});

test('delete mid-flow failure rolls back all deletions', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(HorseCareService::class);
    $horseCare = $svc->create($validData($center->id));
    $serviceId = $horseCare->service->id;
    $horseCareId = $horseCare->id;
    $oldOptionIds = $horseCare->service->priceOptions()->pluck('id')->all();

    $failingDeleter = new class extends HorseCareService
    {
        public function delete(HorseCare $horseCare): void
        {
            DB::transaction(function () use ($horseCare) {
                $service = $horseCare->service()->lockForUpdate()->firstOrFail();
                $service->priceOptions()->delete();
                throw new RuntimeException('intentional mid-delete fail');
            });
        }
    };

    try {
        $failingDeleter->delete($horseCare);
        $this->fail('expected exception not thrown');
    } catch (RuntimeException $e) {
    }

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(HorseCare::query()->find($horseCareId))->not->toBeNull();
    expect(PriceOption::query()->whereIn('id', $oldOptionIds)->count())->toBe(2);
});
