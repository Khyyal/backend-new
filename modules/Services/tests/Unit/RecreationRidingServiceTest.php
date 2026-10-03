<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;
use Modules\Services\Services\RecreationRidingService;
use Modules\Support\Enums\ActivationStatus;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $paths = [
        base_path('modules/Support/database/migrations'),
        base_path('modules/Centers/database/migrations'),
        base_path('modules/Services/database/migrations'),
    ];
    foreach ($paths as $path) {
        foreach (glob($path . '/*.php') as $file) {
            require_once $file;
        }
    }
    Artisan::call('migrate', [
        '--path' => $paths,
        '--realpath' => true,
    ]);
});

$validCreateData = function (int $centerId): array {
    return [
        'center_id' => $centerId,
        'name' => ['ar' => 'ركوب ترفيهي', 'en' => 'Recreational Riding'],
        'description' => ['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description'],
        'price_options' => [
            ['duration' => 30, 'price' => 100.50],
            ['duration' => 60, 'price' => 180.75],
        ],
        'days' => [0, 2, 4],
        'hours' => ['09:00', '12:00', '14:00', '17:00'],
    ];
};

// ────────────────────────────────────────────────────────────────────
//  CREATE — Service creation (entity hierarchy)
// ────────────────────────────────────────────────────────────────────

test('create builds full graph: counts increment for all 4 entities', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);

    $before = [
        'riding' => RecreationalRiding::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
        'schedule' => Schedule::count(),
    ];

    $riding = $svc->create($validCreateData($center->id));

    expect(RecreationalRiding::withTrashed()->count())->toBe($before['riding'] + 1);
    expect(Service::withTrashed()->count())->toBe($before['service'] + 1);
    expect(PriceOption::count())->toBe($before['option'] + 2);
    expect(Schedule::count())->toBe($before['schedule'] + 6); // 3 days × 2 slots
    expect($riding)->toBeInstanceOf(RecreationalRiding::class);
});

test('create: Service row has correct enum/center metadata', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    $service = $riding->service;
    expect($service->type)->toBe(ServiceType::RecreationRiding);
    expect($service->status)->toBe(ActivationStatus::ACTIVE);
    expect($service->center_id)->toBe($center->id);
    expect($service->slug)->not->toBeEmpty();
    expect($service->serviceable_type)->toBe(RecreationalRiding::class);
    expect($service->serviceable_id)->toBe($riding->id);
});

test('create: morphOne Service ↔ RecreationalRiding bidirectional link', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    $serviceFromRelation = $riding->service;
    $serviceable = $serviceFromRelation->serviceable;
    expect($serviceable->is($riding))->toBeTrue();
});

test('create: translatable JSON (ar required, en optional', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);

    $data = $validCreateData($center->id);
    $riding = $svc->create($data);
    expect($riding->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'ركوب ترفيهي', 'en' => 'Recreational Riding']);
    expect($riding->service->getTranslations('description'))
        ->toMatchArray(['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description']);

    // ar-only variant
    $arOnly = [
        'center_id' => $center->id,
        'name' => ['ar' => 'فقط عربي'],
        'description' => ['ar' => 'وصف عربي فقط'],
        'price_options' => [['duration' => 45, 'price' => 90]],
        'days' => [1],
        'hours' => ['10:00', '11:00'],
    ];
    $r2 = $svc->create($arOnly);
    $name = $r2->service->getTranslations('name');
    $desc = $r2->service->getTranslations('description');
    expect($name)->toHaveKey('ar', 'فقط عربي');
    expect($name)->not->toHaveKey('en');
    expect($desc)->toHaveKey('ar', 'وصف عربي فقط');
    expect($desc)->not->toHaveKey('en');
});

test('create: PriceOptions correct column mapping + count', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    $options = $riding->service->priceOptions()->orderBy('quantity')->get();
    expect($options)->toHaveCount(2);
    expect($options[0]->quantity)->toBe(30);
    expect($options[0]->price)->toBe(100.50);
    expect($options[0]->unit)->toBe(PriceOptionUnit::MINUTE);
    expect($options[0]->name)->toBeNull();
    expect($options[1]->quantity)->toBe(60);
    expect($options[1]->price)->toBe(180.75);
    expect($options[1]->unit)->toBe(PriceOptionUnit::MINUTE);
});

test('create: Schedules count + cartesian days × slot pairs with polymorphic FKs', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    $schedules = $riding->schedules;
    expect($schedules)->toHaveCount(3 * 2);
    expect($schedules->pluck('day_of_week')->unique()->sort()->values()->all())->toBe([0, 2, 4]);

    foreach ([0, 2, 4] as $d) {
        $dayRows = $schedules->where('day_of_week', $d)->sortBy('start_time')->values();
        expect(substr($dayRows[0]->start_time, 0, 5))->toBe('09:00');
        expect(substr($dayRows[0]->end_time, 0, 5))->toBe('12:00');
        expect(substr($dayRows[1]->start_time, 0, 5))->toBe('14:00');
        expect(substr($dayRows[1]->end_time, 0, 5))->toBe('17:00');
        expect($dayRows[0]->schedulable_type)->toBe(RecreationalRiding::class);
        expect($dayRows[0]->schedulable_id)->toBe($riding->id);
    }
});

test('create: returned model eager-loads service, service.priceOptions, schedules relations', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    expect($riding->relationLoaded('service'))->toBeTrue();
    expect($riding->service->relationLoaded('priceOptions'))->toBeTrue();
    expect($riding->relationLoaded('schedules'))->toBeTrue();

    // ensure no lazy loads happen
    DB::enableQueryLog();
    $riding->service->getAttribute('service');
    $riding->service->getRelation('priceOptions');
    $riding->getAttribute('schedules');
    expect(DB::getQueryLog())->toHaveCount(0);
});

// ────────────────────────────────────────────────────────────────────
//  UPDATE
// ────────────────────────────────────────────────────────────────────

test('update: only translations change; slug/center_id/type/status preserved', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));
    $old = $riding->service->replicate();

    $newData = [
        'name' => ['ar' => 'جديد', 'en' => 'Updated'],
        'description' => ['ar' => 'وصف جديد', 'en' => 'New description'],
        'price_options' => [['duration' => 15, 'price' => 50]],
        'days' => [6],
        'hours' => ['08:00', '09:30'],
    ];
    $updated = $svc->update($riding, $newData);

    expect($updated->service->slug)->toBe($old->slug);
    expect($updated->service->center_id)->toBe($old->center_id);
    expect($updated->service->type)->toBe($old->type);
    expect($updated->service->status)->toBe($old->status);
    expect($updated->service->serviceable_type)->toBe($old->serviceable_type);
    expect($updated->service->serviceable_id)->toBe($old->serviceable_id);
    expect($updated->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'جديد', 'en' => 'Updated']);
    expect($updated->service->getTranslations('description'))
        ->toMatchArray(['ar' => 'وصف جديد', 'en' => 'New description']);
});

test('update: PriceOptions full sync delete-then-recreate no stale IDs', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));
    $oldIds = $riding->service->priceOptions()->pluck('id')->all();
    expect($oldIds)->toHaveCount(2);

    $newOpts = [
        ['duration' => 10, 'price' => 25],
        ['duration' => 25, 'price' => 55],
        ['duration' => 90, 'price' => 250],
    ];
    $newData = array_replace($validCreateData($center->id), ['price_options' => $newOpts]);
    $updated = $svc->update($riding, $newData);

    expect(PriceOption::query()->whereIn('id', $oldIds)->count())->toBe(0);
    $opts = $updated->service->priceOptions()->orderBy('quantity')->get();
    expect($opts)->toHaveCount(3);
    expect($opts->pluck('quantity')->all())->toBe([10, 25, 90]);
    expect($opts->pluck('price')->all())->toBe([25.0, 55.0, 250.0]);
    foreach ($opts as $o) {
        expect($o->unit)->toBe(PriceOptionUnit::MINUTE);
        expect($o->name)->toBeNull();
    }
});

test('update: Schedules full sync delete-then-recreate no stale IDs', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));
    $oldScheduleIds = $riding->schedules()->pluck('id')->all();
    expect($oldScheduleIds)->toHaveCount(6);

    $newDays = [1, 6];
    $newHours = ['10:00', '11:30', '13:00', '15:00', '16:00', '18:00']; // 3 pairs
    $newData = array_replace($validCreateData($center->id), ['days' => $newDays, 'hours' => $newHours]);
    $updated = $svc->update($riding, $newData);

    expect(Schedule::query()->whereIn('id', $oldScheduleIds)->count())->toBe(0);
    $sch = $updated->schedules;
    expect($sch)->toHaveCount(2 * 3); // 2 days × 3 pairs
    expect($sch->pluck('day_of_week')->unique()->sort()->values()->all())->toBe([1, 6]);
    foreach ($sch as $s) {
        expect($s->schedulable_type)->toBe(RecreationalRiding::class);
        expect($s->schedulable_id)->toBe($updated->id);
    }
});

test('update: returned model is fresh with all 3 relations preloaded', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    $newData = array_replace($validCreateData($center->id), [
        'price_options' => [['duration' => 20, 'price' => 40]],
        'days' => [3],
        'hours' => ['07:00', '08:00'],
    ]);
    $updated = $svc->update($riding, $newData);

    expect($updated->relationLoaded('service'))->toBeTrue();
    expect($updated->service->relationLoaded('priceOptions'))->toBeTrue();
    expect($updated->relationLoaded('schedules'))->toBeTrue();
    expect($updated->service->priceOptions)->toHaveCount(1);
    expect($updated->schedules)->toHaveCount(1);
    expect($updated->wasRecentlyCreated)->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────
//  DELETE
// ────────────────────────────────────────────────────────────────────

test('delete: schedules + priceOptions removed; service and riding soft-deleted', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    $serviceId = $riding->service->id;
    $ridingId = $riding->id;

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(RecreationalRiding::query()->find($ridingId))->not->toBeNull();
    expect(PriceOption::query()->count())->toBe(2);
    expect(Schedule::query()->count())->toBe(6);

    $svc->delete($riding);

    // hard-deleted (no soft deletes on pivot/children)
    expect(PriceOption::query()->count())->toBe(0);
    expect(Schedule::query()->count())->toBe(0);

    // soft-deleted (trashed)
    expect(Service::query()->find($serviceId))->toBeNull();
    expect(RecreationalRiding::query()->find($ridingId))->toBeNull();
    expect(Service::withTrashed()->find($serviceId))->not->toBeNull();
    expect(RecreationalRiding::withTrashed()->find($ridingId))->not->toBeNull();
});

test('delete: returns void', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));

    $result = $svc->delete($riding);
    expect($result)->toBeNull();
});

// ────────────────────────────────────────────────────────────────────
//  TRANSACTIONS (rollback)
// ────────────────────────────────────────────────────────────────────

test('create mid-flow failure rolls back all 4 tables', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $before = [
        'riding' => RecreationalRiding::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
        'schedule' => Schedule::count(),
    ];

    $failingSvc = new class() extends RecreationRidingService {
        public function create(array $data): RecreationalRiding {
            return DB::transaction(function () use ($data) {
                $riding = RecreationalRiding::query()->create();
                $riding->service()->create([
                    'name' => ['ar' => $data['name']['ar']],
                    'description' => ['ar' => $data['description']['ar']],
                    'center_id' => $data['center_id'],
                    'status' => ActivationStatus::ACTIVE,
                    'type' => ServiceType::RecreationRiding,
                ]);
                throw new \RuntimeException('intentional mid-create fail');
            });
        }
    };

    try {
        $failingSvc->create($validCreateData($center->id));
        $this->fail('expected exception not thrown');
    } catch (\RuntimeException $e) {
    }

    expect(RecreationalRiding::withTrashed()->count())->toBe($before['riding']);
    expect(Service::withTrashed()->count())->toBe($before['service']);
    expect(PriceOption::count())->toBe($before['option']);
    expect(Schedule::count())->toBe($before['schedule']);
});

test('update mid-flow failure rolls back translation, option sync, schedule sync', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));
    $oldNameAr = $riding->service->getTranslation('name', 'ar');
    $oldOptionIds = $riding->service->priceOptions()->pluck('id')->all();
    $oldScheduleIds = $riding->schedules()->pluck('id')->all();

    $failingUpdater = new class() extends RecreationRidingService {
        public function update(RecreationalRiding $riding, array $data): RecreationalRiding {
            return DB::transaction(function () use ($riding, $data) {
                $service = $riding->service()->lockForUpdate()->firstOrFail();
                $service->update(['name' => ['ar' => 'TEMP']]);
                $service->priceOptions()->delete();
                $riding->schedules()->delete();
                throw new \RuntimeException('intentional mid-update fail');
            });
        }
    };

    try {
        $failingUpdater->update($riding, $validCreateData($center->id));
        $this->fail('expected exception not thrown');
    } catch (\RuntimeException $e) {
    }

    $riding->refresh();
    expect($riding->service->getTranslation('name', 'ar'))->toBe($oldNameAr);
    expect(PriceOption::query()->whereIn('id', $oldOptionIds)->count())->toBe(2);
    expect(Schedule::query()->whereIn('id', $oldScheduleIds)->count())->toBe(6);
});

test('delete mid-flow failure rolls back all deletions', function () use ($validCreateData) {
    $center = CenterFactory::new()->create();
    $svc = app(RecreationRidingService::class);
    $riding = $svc->create($validCreateData($center->id));
    $serviceId = $riding->service->id;
    $ridingId = $riding->id;
    $oldOptionIds = $riding->service->priceOptions()->pluck('id')->all();
    $oldScheduleIds = $riding->schedules()->pluck('id')->all();

    $failingDeleter = new class() extends RecreationRidingService {
        public function delete(RecreationalRiding $riding): void {
            DB::transaction(function () use ($riding) {
                $service = $riding->service()->lockForUpdate()->firstOrFail();
                $riding->schedules()->delete();
                $service->priceOptions()->delete();
                throw new \RuntimeException('intentional mid-delete fail');
            });
        }
    };

    try {
        $failingDeleter->delete($riding);
        $this->fail('expected exception not thrown');
    } catch (\RuntimeException $e) {
    }

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(RecreationalRiding::query()->find($ridingId))->not->toBeNull();
    expect(PriceOption::query()->whereIn('id', $oldOptionIds)->count())->toBe(2);
    expect(Schedule::query()->whereIn('id', $oldScheduleIds)->count())->toBe(6);
});

// ────────────────────────────────────────────────────────────────────
//  CONCURRENCY / STRUCTURE
// ────────────────────────────────────────────────────────────────────

test('source contains two transaction wrappers + lockForUpdate in update + delete', function () {
    $src = file_get_contents(__DIR__ . '/../../src/Services/RecreationRidingService.php');

    $createPos = strpos($src, 'public function create');
    $updatePos = strpos($src, 'public function update');
    $deletePos = strpos($src, 'public function delete');
    $createBody = substr($src, $createPos, $updatePos - $createPos);
    $updateBody = substr($src, $updatePos, $deletePos - $updatePos);
    $deleteBody = substr($src, $deletePos);

    expect(str_contains($createBody, 'DB::transaction'))->toBeTrue();
    expect(str_contains($updateBody, 'DB::transaction'))->toBeTrue();
    expect(str_contains($deleteBody, 'DB::transaction'))->toBeTrue();

    // lockForUpdate used in update & delete (not in create since both new rows)
    expect(str_contains($updateBody, 'lockForUpdate'))->toBeTrue();
    expect(str_contains($deleteBody, 'lockForUpdate'))->toBeTrue();

    // morphOne usage: service()->create (in create) and service()->lockForUpdate (in update/delete)
    expect(str_contains($createBody, "service()->create"))->toBeTrue();
});
