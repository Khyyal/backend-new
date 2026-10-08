<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceBlock;
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

function createServiceFor(object $serviceable, int $centerId): Service
{
    return $serviceable->service()->create([
        'name' => ['ar' => 'اسم'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $centerId,
        'status' => ActivationStatus::ACTIVE,
        'type' => ServiceType::Resort,
    ]);
}

// ────────────────────────────────────────────────────────────────────
//  forService
// ────────────────────────────────────────────────────────────────────

test('forService matches a block with no service_id (applies to all)', function () {
    $center = CenterFactory::new()->create();
    $service = createServiceFor(Resort::query()->create(), $center->id);

    ServiceBlock::query()->create([
        'center_id' => $center->id,
        'service_id' => null,
        'reason' => 'Maintenance',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-05',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    expect(ServiceBlock::query()->forService($service)->count())->toBe(1);
});

test('forService matches a block targeting the exact service', function () {
    $center = CenterFactory::new()->create();
    $service = createServiceFor(Resort::query()->create(), $center->id);
    $otherService = createServiceFor(Resort::query()->create(), $center->id);

    ServiceBlock::query()->create([
        'center_id' => $center->id,
        'service_id' => $service->id,
        'reason' => 'Maintenance',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-05',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    expect(ServiceBlock::query()->forService($service)->count())->toBe(1);
    expect(ServiceBlock::query()->forService($otherService)->count())->toBe(0);
});

// ────────────────────────────────────────────────────────────────────
//  activeOn (date range)
// ────────────────────────────────────────────────────────────────────

test('activeOn matches a date inside the range and not outside it', function () {
    $center = CenterFactory::new()->create();
    ServiceBlock::query()->create([
        'center_id' => $center->id,
        'service_id' => null,
        'reason' => 'Closed',
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-15',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    expect(ServiceBlock::query()->activeOn('2026-02-12')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-02-10')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-02-15')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-02-16')->exists())->toBeFalse();
    expect(ServiceBlock::query()->activeOn('2026-02-09')->exists())->toBeFalse();
});

test('activeOn matches a single-day block only on that date', function () {
    $center = CenterFactory::new()->create();
    ServiceBlock::query()->create([
        'center_id' => $center->id,
        'service_id' => null,
        'reason' => 'Closed for the day',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-01',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    expect(ServiceBlock::query()->activeOn('2026-03-01')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-03-02')->exists())->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────
//  activeOn (time scope)
// ────────────────────────────────────────────────────────────────────

test('all_day block matches regardless of the time given', function () {
    $center = CenterFactory::new()->create();
    ServiceBlock::query()->create([
        'center_id' => $center->id,
        'service_id' => null,
        'reason' => 'Closed all day',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-01',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    expect(ServiceBlock::query()->activeOn('2026-04-01')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-04-01', '09:00')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-04-01', '23:00')->exists())->toBeTrue();
});

test('specific_time block only matches a time within its period', function () {
    $center = CenterFactory::new()->create();
    $block = ServiceBlock::query()->create([
        'center_id' => $center->id,
        'service_id' => null,
        'reason' => 'Closed for cleaning',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
        'time_scope' => BlockTimeScope::SpecificTime,
    ]);
    $block->periods()->create(['start_time' => '09:00', 'end_time' => '12:00']);

    expect(ServiceBlock::query()->activeOn('2026-05-01', '10:00')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-05-01', '09:00')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-05-01', '12:00')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-05-01', '13:00')->exists())->toBeFalse();
    expect(ServiceBlock::query()->activeOn('2026-05-01')->exists())->toBeFalse();
});

test('specific_time block with two disjoint periods matches either but not the gap', function () {
    $center = CenterFactory::new()->create();
    $block = ServiceBlock::query()->create([
        'center_id' => $center->id,
        'service_id' => null,
        'reason' => 'Closed for cleaning and restocking',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-01',
        'time_scope' => BlockTimeScope::SpecificTime,
    ]);
    $block->periods()->create(['start_time' => '12:00', 'end_time' => '13:00']);
    $block->periods()->create(['start_time' => '19:00', 'end_time' => '20:00']);

    expect(ServiceBlock::query()->activeOn('2026-06-01', '12:30')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-06-01', '19:30')->exists())->toBeTrue();
    expect(ServiceBlock::query()->activeOn('2026-06-01', '15:00')->exists())->toBeFalse();
    expect(ServiceBlock::query()->activeOn('2026-06-01', '21:00')->exists())->toBeFalse();
});
