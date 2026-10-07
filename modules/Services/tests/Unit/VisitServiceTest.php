<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;
use Modules\Services\Models\Visit;
use Modules\Services\Services\VisitService;
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

$specificTimeData = function (int $centerId): array {
    return [
        'center_id' => $centerId,
        'name' => ['ar' => 'زيارة', 'en' => 'Visit'],
        'description' => ['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description'],
        'enter_type' => VisitEnterType::SpecificTime->value,
        'price' => 150.50,
        'days' => [0, 2, 4],
        'hours' => ['09:00', '12:00', '14:00', '17:00'],
    ];
};

$allDayData = function (int $centerId): array {
    return [
        'center_id' => $centerId,
        'name' => ['ar' => 'زيارة', 'en' => 'Visit'],
        'description' => ['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description'],
        'enter_type' => VisitEnterType::AllDay->value,
        'price' => 150.50,
        'days' => [0, 2, 4],
    ];
};

// ────────────────────────────────────────────────────────────────────
//  CREATE
// ────────────────────────────────────────────────────────────────────

test('create builds full graph: counts increment for all 4 entities', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);

    $before = [
        'visit' => Visit::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
        'schedule' => Schedule::count(),
    ];

    $visit = $svc->create($specificTimeData($center->id));

    expect(Visit::withTrashed()->count())->toBe($before['visit'] + 1);
    expect(Service::withTrashed()->count())->toBe($before['service'] + 1);
    expect(PriceOption::count())->toBe($before['option'] + 1);
    expect(Schedule::count())->toBe($before['schedule'] + 6); // 3 days × 2 slots
    expect($visit)->toBeInstanceOf(Visit::class);
});

test('create: Service row has correct enum/center metadata', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    $service = $visit->service;
    expect($service->type)->toBe(ServiceType::Visit);
    expect($service->status)->toBe(ActivationStatus::ACTIVE);
    expect($service->center_id)->toBe($center->id);
    expect($service->slug)->not->toBeEmpty();
    expect($service->serviceable_type)->toBe(Visit::class);
    expect($service->serviceable_id)->toBe($visit->id);
});

test('create: morphOne Service ↔ Visit bidirectional link', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    $serviceFromRelation = $visit->service;
    $serviceable = $serviceFromRelation->serviceable;
    expect($serviceable->is($visit))->toBeTrue();
});

test('create: translatable JSON (ar required, en optional)', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);

    $visit = $svc->create($specificTimeData($center->id));
    expect($visit->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'زيارة', 'en' => 'Visit']);
    expect($visit->service->getTranslations('description'))
        ->toMatchArray(['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description']);

    $arOnly = [
        'center_id' => $center->id,
        'name' => ['ar' => 'فقط عربي'],
        'description' => ['ar' => 'وصف عربي فقط'],
        'enter_type' => VisitEnterType::AllDay->value,
        'price' => 50,
        'days' => [1],
    ];
    $r2 = $svc->create($arOnly);
    $name = $r2->service->getTranslations('name');
    $desc = $r2->service->getTranslations('description');
    expect($name)->toHaveKey('ar', 'فقط عربي');
    expect($name)->not->toHaveKey('en');
    expect($desc)->toHaveKey('ar', 'وصف عربي فقط');
    expect($desc)->not->toHaveKey('en');
});

test('create: single PriceOption has price only (no duration/name)', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    $options = $visit->service->priceOptions()->get();
    expect($options)->toHaveCount(1);
    expect($options[0]->price)->toBe(150.50);
    expect($options[0]->quantity)->toBeNull();
    expect($options[0]->name)->toBeNull();
    expect($options[0]->unit)->toBe(PriceOptionUnit::OPTION);
});

test('create: specific_time schedules cartesian days × slot pairs with times', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    $schedules = $visit->schedules;
    expect($schedules)->toHaveCount(3 * 2);
    expect($schedules->pluck('day_of_week')->unique()->sort()->values()->all())->toBe([0, 2, 4]);

    foreach ([0, 2, 4] as $d) {
        $dayRows = $schedules->where('day_of_week', $d)->sortBy('start_time')->values();
        expect(substr($dayRows[0]->start_time, 0, 5))->toBe('09:00');
        expect(substr($dayRows[0]->end_time, 0, 5))->toBe('12:00');
        expect(substr($dayRows[1]->start_time, 0, 5))->toBe('14:00');
        expect(substr($dayRows[1]->end_time, 0, 5))->toBe('17:00');
        expect($dayRows[0]->schedulable_type)->toBe(Visit::class);
        expect($dayRows[0]->schedulable_id)->toBe($visit->id);
    }
});

test('create: all_day schedules store only day_of_week, no times', function () use ($allDayData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($allDayData($center->id));

    $schedules = $visit->schedules;
    expect($schedules)->toHaveCount(3); // one row per day, no slots
    expect($schedules->pluck('day_of_week')->unique()->sort()->values()->all())->toBe([0, 2, 4]);

    foreach ($schedules as $schedule) {
        expect($schedule->start_time)->toBeNull();
        expect($schedule->end_time)->toBeNull();
        expect($schedule->schedulable_type)->toBe(Visit::class);
        expect($schedule->schedulable_id)->toBe($visit->id);
    }
});

test('create: returned model eager-loads service, service.priceOptions, schedules relations', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    expect($visit->relationLoaded('service'))->toBeTrue();
    expect($visit->service->relationLoaded('priceOptions'))->toBeTrue();
    expect($visit->relationLoaded('schedules'))->toBeTrue();

    DB::enableQueryLog();
    $visit->service->getAttribute('service');
    $visit->service->getRelation('priceOptions');
    $visit->getAttribute('schedules');
    expect(DB::getQueryLog())->toHaveCount(0);
});

// ────────────────────────────────────────────────────────────────────
//  UPDATE
// ────────────────────────────────────────────────────────────────────

test('update: translations + enter_type change; slug/center_id/type/status preserved', function () use ($specificTimeData, $allDayData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));
    $old = $visit->service->replicate();

    $newData = array_replace($allDayData($center->id), [
        'name' => ['ar' => 'جديد', 'en' => 'Updated'],
        'description' => ['ar' => 'وصف جديد', 'en' => 'New description'],
    ]);
    $updated = $svc->update($visit, $newData);

    expect($updated->service->slug)->toBe($old->slug);
    expect($updated->service->center_id)->toBe($old->center_id);
    expect($updated->service->type)->toBe($old->type);
    expect($updated->service->status)->toBe($old->status);
    expect($updated->service->serviceable_type)->toBe($old->serviceable_type);
    expect($updated->service->serviceable_id)->toBe($old->serviceable_id);
    expect($updated->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'جديد', 'en' => 'Updated']);
    expect($updated->enter_type)->toBe(VisitEnterType::AllDay);
});

test('update: PriceOption single-row replace, no stale IDs', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));
    $oldIds = $visit->service->priceOptions()->pluck('id')->all();
    expect($oldIds)->toHaveCount(1);

    $newData = array_replace($specificTimeData($center->id), ['price' => 999]);
    $updated = $svc->update($visit, $newData);

    expect(PriceOption::query()->whereIn('id', $oldIds)->count())->toBe(0);
    $opts = $updated->service->priceOptions()->get();
    expect($opts)->toHaveCount(1);
    expect($opts[0]->price)->toBe(999.0);
    expect($opts[0]->quantity)->toBeNull();
    expect($opts[0]->unit)->toBe(PriceOptionUnit::OPTION);
});

test('update: Schedules full sync delete-then-recreate no stale IDs', function () use ($specificTimeData, $allDayData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));
    $oldScheduleIds = $visit->schedules()->pluck('id')->all();
    expect($oldScheduleIds)->toHaveCount(6);

    $newData = array_replace($allDayData($center->id), ['days' => [1, 6]]);
    $updated = $svc->update($visit, $newData);

    expect(Schedule::query()->whereIn('id', $oldScheduleIds)->count())->toBe(0);
    $sch = $updated->schedules;
    expect($sch)->toHaveCount(2);
    expect($sch->pluck('day_of_week')->unique()->sort()->values()->all())->toBe([1, 6]);
    foreach ($sch as $s) {
        expect($s->start_time)->toBeNull();
        expect($s->end_time)->toBeNull();
        expect($s->schedulable_type)->toBe(Visit::class);
        expect($s->schedulable_id)->toBe($updated->id);
    }
});

test('update: returned model is fresh with all 3 relations preloaded', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    $newData = array_replace($specificTimeData($center->id), [
        'days' => [3],
        'hours' => ['07:00', '08:00'],
    ]);
    $updated = $svc->update($visit, $newData);

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

test('delete: schedules + priceOption removed; service and visit soft-deleted', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    $serviceId = $visit->service->id;
    $visitId = $visit->id;

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(Visit::query()->find($visitId))->not->toBeNull();
    expect(PriceOption::query()->count())->toBe(1);
    expect(Schedule::query()->count())->toBe(6);

    $svc->delete($visit);

    expect(PriceOption::query()->count())->toBe(0);
    expect(Schedule::query()->count())->toBe(0);

    expect(Service::query()->find($serviceId))->toBeNull();
    expect(Visit::query()->find($visitId))->toBeNull();
    expect(Service::withTrashed()->find($serviceId))->not->toBeNull();
    expect(Visit::withTrashed()->find($visitId))->not->toBeNull();
});

test('delete: returns void', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));

    $result = $svc->delete($visit);
    expect($result)->toBeNull();
});

// ────────────────────────────────────────────────────────────────────
//  TRANSACTIONS (rollback)
// ────────────────────────────────────────────────────────────────────

test('create mid-flow failure rolls back all 4 tables', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $before = [
        'visit' => Visit::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
        'schedule' => Schedule::count(),
    ];

    $failingSvc = new class extends VisitService
    {
        public function create(array $data): Visit
        {
            return DB::transaction(function () use ($data) {
                $visit = Visit::query()->create(['enter_type' => $data['enter_type']]);
                $visit->service()->create([
                    'name' => ['ar' => $data['name']['ar']],
                    'description' => ['ar' => $data['description']['ar']],
                    'center_id' => $data['center_id'],
                    'status' => ActivationStatus::ACTIVE,
                    'type' => ServiceType::Visit,
                ]);
                throw new RuntimeException('intentional mid-create fail');
            });
        }
    };

    try {
        $failingSvc->create($specificTimeData($center->id));
        $this->fail('expected exception not thrown');
    } catch (RuntimeException $e) {
    }

    expect(Visit::withTrashed()->count())->toBe($before['visit']);
    expect(Service::withTrashed()->count())->toBe($before['service']);
    expect(PriceOption::count())->toBe($before['option']);
    expect(Schedule::count())->toBe($before['schedule']);
});

test('delete mid-flow failure rolls back all deletions', function () use ($specificTimeData) {
    $center = CenterFactory::new()->create();
    $svc = app(VisitService::class);
    $visit = $svc->create($specificTimeData($center->id));
    $serviceId = $visit->service->id;
    $visitId = $visit->id;
    $oldOptionIds = $visit->service->priceOptions()->pluck('id')->all();
    $oldScheduleIds = $visit->schedules()->pluck('id')->all();

    $failingDeleter = new class extends VisitService
    {
        public function delete(Visit $visit): void
        {
            DB::transaction(function () use ($visit) {
                $service = $visit->service()->lockForUpdate()->firstOrFail();
                $visit->schedules()->delete();
                $service->priceOptions()->delete();
                throw new RuntimeException('intentional mid-delete fail');
            });
        }
    };

    try {
        $failingDeleter->delete($visit);
        $this->fail('expected exception not thrown');
    } catch (RuntimeException $e) {
    }

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(Visit::query()->find($visitId))->not->toBeNull();
    expect(PriceOption::query()->whereIn('id', $oldOptionIds)->count())->toBe(1);
    expect(Schedule::query()->whereIn('id', $oldScheduleIds)->count())->toBe(6);
});
