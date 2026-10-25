<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\EventOccurrenceType;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Event;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Services\EventService;
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
        'name' => ['ar' => 'فعالية', 'en' => 'Event'],
        'description' => ['ar' => 'وصف الخدمة بالعربية', 'en' => 'English service description'],
        'occurrence_type' => EventOccurrenceType::General->value,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-05',
        'open_date' => '2026-10-01',
        'close_date' => '2026-10-31',
        'max_tickets_per_day' => 100,
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
    $svc = app(EventService::class);

    $before = [
        'event' => Event::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $event = $svc->create($validData($center->id));

    expect(Event::withTrashed()->count())->toBe($before['event'] + 1);
    expect(Service::withTrashed()->count())->toBe($before['service'] + 1);
    expect(PriceOption::count())->toBe($before['option'] + 2);
    expect($event)->toBeInstanceOf(Event::class);
});

test('create: Service row has correct enum/center metadata', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    $service = $event->service;
    expect($service->type)->toBe(ServiceType::Event);
    expect($service->status)->toBe(ActivationStatus::ACTIVE);
    expect($service->center_id)->toBe($center->id);
    expect($service->slug)->not->toBeEmpty();
    expect($service->serviceable_type)->toBe(Event::class);
    expect($service->serviceable_id)->toBe($event->id);
});

test('create: morphOne Service ↔ Event bidirectional link', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    $serviceFromRelation = $event->service;
    $serviceable = $serviceFromRelation->serviceable;
    expect($serviceable->is($event))->toBeTrue();
});

test('create: translatable JSON (ar required, en optional)', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);

    $event = $svc->create($validData($center->id));
    expect($event->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'فعالية', 'en' => 'Event']);
    expect($event->service->getTranslations('description'))
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

test('create: Event row stores dates and max_tickets_per_day', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    expect($event->occurrence_type)->toBe(EventOccurrenceType::General);
    expect($event->start_date->format('Y-m-d'))->toBe('2026-11-01');
    expect($event->end_date->format('Y-m-d'))->toBe('2026-11-05');
    expect($event->open_date->format('Y-m-d'))->toBe('2026-10-01');
    expect($event->close_date->format('Y-m-d'))->toBe('2026-10-31');
    expect($event->max_tickets_per_day)->toBe(100);
});

test('create: PriceOptions correct column mapping (name+price, no quantity) + count', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    $options = $event->service->priceOptions()->orderBy('price')->get();
    expect($options)->toHaveCount(2);
    expect($options[0]->name)->toBe('Child');
    expect($options[0]->price)->toBe(25.0);
    expect($options[0]->quantity)->toBeNull();
    expect($options[0]->unit)->toBe(PriceOptionUnit::OPTION);
    expect($options[1]->name)->toBe('Adult');
    expect($options[1]->price)->toBe(50.0);
});

test('create: returned model eager-loads service and service.priceOptions relations', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    expect($event->relationLoaded('service'))->toBeTrue();
    expect($event->service->relationLoaded('priceOptions'))->toBeTrue();

    DB::enableQueryLog();
    $event->service->getAttribute('service');
    $event->service->getRelation('priceOptions');
    expect(DB::getQueryLog())->toHaveCount(0);
});

// ────────────────────────────────────────────────────────────────────
//  UPDATE
// ────────────────────────────────────────────────────────────────────

test('update: translations + dates change; slug/center_id/type/status preserved', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));
    $old = $event->service->replicate();

    $newData = array_replace($validData($center->id), [
        'name' => ['ar' => 'جديد', 'en' => 'Updated'],
        'description' => ['ar' => 'وصف جديد', 'en' => 'New description'],
        'occurrence_type' => EventOccurrenceType::SpecificDay->value,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
    ]);
    $updated = $svc->update($event, $newData);

    expect($updated->service->slug)->toBe($old->slug);
    expect($updated->service->center_id)->toBe($old->center_id);
    expect($updated->service->type)->toBe($old->type);
    expect($updated->service->status)->toBe($old->status);
    expect($updated->service->serviceable_type)->toBe($old->serviceable_type);
    expect($updated->service->serviceable_id)->toBe($old->serviceable_id);
    expect($updated->service->getTranslations('name'))
        ->toMatchArray(['ar' => 'جديد', 'en' => 'Updated']);
    expect($updated->occurrence_type)->toBe(EventOccurrenceType::SpecificDay);
    expect($updated->start_date->format('Y-m-d'))->toBe('2026-12-01');
});

test('update: PriceOptions full sync delete-then-recreate no stale IDs', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));
    $oldIds = $event->service->priceOptions()->pluck('id')->all();
    expect($oldIds)->toHaveCount(2);

    $newOpts = [
        ['name' => 'VIP', 'price' => 200],
        ['name' => 'Standard', 'price' => 80],
        ['name' => 'Student', 'price' => 40],
    ];
    $newData = array_replace($validData($center->id), ['price_options' => $newOpts]);
    $updated = $svc->update($event, $newData);

    expect(PriceOption::query()->whereIn('id', $oldIds)->count())->toBe(0);
    $opts = $updated->service->priceOptions()->orderBy('price')->get();
    expect($opts)->toHaveCount(3);
    expect($opts->pluck('name')->all())->toBe(['Student', 'Standard', 'VIP']);
    foreach ($opts as $o) {
        expect($o->unit)->toBe(PriceOptionUnit::OPTION);
        expect($o->quantity)->toBeNull();
    }
});

test('update: returned model is fresh with relations preloaded', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    $newData = array_replace($validData($center->id), [
        'price_options' => [['name' => 'Single', 'price' => 20]],
    ]);
    $updated = $svc->update($event, $newData);

    expect($updated->relationLoaded('service'))->toBeTrue();
    expect($updated->service->relationLoaded('priceOptions'))->toBeTrue();
    expect($updated->service->priceOptions)->toHaveCount(1);
    expect($updated->wasRecentlyCreated)->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────
//  DELETE
// ────────────────────────────────────────────────────────────────────

test('delete: priceOptions removed; service and event soft-deleted', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    $serviceId = $event->service->id;
    $eventId = $event->id;

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(Event::query()->find($eventId))->not->toBeNull();
    expect(PriceOption::query()->count())->toBe(2);

    $svc->delete($event);

    expect(PriceOption::query()->count())->toBe(0);

    expect(Service::query()->find($serviceId))->toBeNull();
    expect(Event::query()->find($eventId))->toBeNull();
    expect(Service::withTrashed()->find($serviceId))->not->toBeNull();
    expect(Event::withTrashed()->find($eventId))->not->toBeNull();
});

test('delete: returns void', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));

    $result = $svc->delete($event);
    expect($result)->toBeNull();
});

// ────────────────────────────────────────────────────────────────────
//  TRANSACTIONS (rollback)
// ────────────────────────────────────────────────────────────────────

test('create mid-flow failure rolls back all 3 tables', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $before = [
        'event' => Event::withTrashed()->count(),
        'service' => Service::withTrashed()->count(),
        'option' => PriceOption::count(),
    ];

    $failingSvc = new class extends EventService
    {
        public function create(array $data): Event
        {
            return DB::transaction(function () use ($data) {
                $event = Event::query()->create([
                    'occurrence_type' => $data['occurrence_type'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'open_date' => $data['open_date'],
                    'close_date' => $data['close_date'],
                    'max_tickets_per_day' => $data['max_tickets_per_day'],
                ]);
                $event->service()->create([
                    'name' => ['ar' => $data['name']['ar']],
                    'description' => ['ar' => $data['description']['ar']],
                    'center_id' => $data['center_id'],
                    'status' => ActivationStatus::ACTIVE,
                    'type' => ServiceType::Event,
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

    expect(Event::withTrashed()->count())->toBe($before['event']);
    expect(Service::withTrashed()->count())->toBe($before['service']);
    expect(PriceOption::count())->toBe($before['option']);
});

test('delete mid-flow failure rolls back all deletions', function () use ($validData) {
    $center = CenterFactory::new()->create();
    $svc = app(EventService::class);
    $event = $svc->create($validData($center->id));
    $serviceId = $event->service->id;
    $eventId = $event->id;
    $oldOptionIds = $event->service->priceOptions()->pluck('id')->all();

    $failingDeleter = new class extends EventService
    {
        public function delete(Event $event): void
        {
            DB::transaction(function () use ($event) {
                $service = $event->service()->lockForUpdate()->firstOrFail();
                $service->priceOptions()->delete();
                throw new RuntimeException('intentional mid-delete fail');
            });
        }
    };

    try {
        $failingDeleter->delete($event);
        $this->fail('expected exception not thrown');
    } catch (RuntimeException $e) {
    }

    expect(Service::query()->find($serviceId))->not->toBeNull();
    expect(Event::query()->find($eventId))->not->toBeNull();
    expect(PriceOption::query()->whereIn('id', $oldOptionIds)->count())->toBe(2);
});
