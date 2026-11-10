<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\ServiceTypeTerm;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    useLegacyServiceSchema();
    CenterFactory::new()->create(['id' => 6]);

    $legacy = DB::connection('legacy');

    $legacy->table('custom_working_hours')->insert([
        ['barn_id' => 6, 'days' => '["sunday"]', 'hours' => '["16:00","17:00"]', 'gender' => 'female'],
        ['barn_id' => 6, 'days' => '["monday"]', 'hours' => '["09:00"]', 'gender' => 'male'],
    ]);

    $legacy->table('service_policies')->insert([
        ['barn_id' => 6, 'service_type' => 'free_riding', 'policy' => 'old policy', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ['barn_id' => 6, 'service_type' => 'free_riding', 'policy' => 'سياسة الجولات', 'created_at' => '2025-02-01 00:00:00', 'updated_at' => '2025-02-01 00:00:00'],
        ['barn_id' => 6, 'service_type' => 'stabling', 'policy' => 'stabling policy', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
    ]);
});

test('gender working hours become center periods', function (): void {
    $this->artisan('migrator:center-settings')->assertSuccessful();

    expect(DB::table('center_periods')->where('center_id', 6)->orderBy('gender')->get(['gender', 'day_of_week', 'start_time', 'end_time'])
        ->map(fn ($row): array => (array) $row)->all())->toBe([
            ['gender' => 'female', 'day_of_week' => 0, 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
            ['gender' => 'male', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00'],
        ]);
});

test('the latest policy per service type becomes its terms and stabling is skipped', function (): void {
    $this->artisan('migrator:center-settings')->assertSuccessful();

    $terms = ServiceTypeTerm::query()->where('center_id', 6)->get();

    expect($terms)->toHaveCount(1)
        ->and($terms->first()->type)->toBe(ServiceType::RecreationRiding)
        ->and($terms->first()->getTranslation('terms', 'ar'))->toBe('سياسة الجولات');
});

test('re-running does not duplicate periods or terms', function (): void {
    $this->artisan('migrator:center-settings')->assertSuccessful();
    $this->artisan('migrator:center-settings')->assertSuccessful();

    expect(DB::table('center_periods')->count())->toBe(2)
        ->and(DB::table('service_type_terms')->count())->toBe(1);
});
