<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\Trainer;
use Modules\Support\Enums\ActivationStatus;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    useLegacyServiceSchema();

    CenterFactory::new()->create(['id' => 6]);

    $this->manager = UserFactory::new()->create();
    $this->manager->centers()->attach(6, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString(), 'is_primary' => false]);

    DB::connection('legacy')->table('trainers')->insert([
        ['id' => 25, 'name' => 'Mohammed', 'barn_id' => 6, 'days' => '["sunday","monday"]', 'hours' => '["06:00","07:00","08:00","09:00","18:00","19:00"]', 'max_tickets_per_hour' => 10, 'barn_manager_id' => $this->manager->id, 'status' => 'active'],
        ['id' => 26, 'name' => 'Sara', 'barn_id' => 6, 'days' => '["friday"]', 'hours' => '["6","7"]', 'max_tickets_per_hour' => 0, 'barn_manager_id' => null, 'status' => 'inactive'],
        ['id' => 27, 'name' => 'Lost', 'barn_id' => 999, 'days' => '[]', 'hours' => '[]', 'max_tickets_per_hour' => null, 'barn_manager_id' => null, 'status' => 'active'],
    ]);
});

test('trainers keep their ids and get merged one-hour slots as schedule windows', function (): void {
    $this->artisan('migrator:trainers')->assertSuccessful();

    $trainer = Trainer::query()->findOrFail(25);

    expect($trainer->center_id)->toBe(6)
        ->and($trainer->max_tickets_per_hour)->toBe(10)
        ->and(DB::table('schedules')->where('schedulable_id', 25)->orderBy('day_of_week')->orderBy('start_time')
            ->get(['day_of_week', 'start_time', 'end_time'])->map(fn ($row): array => (array) $row)->all())
        ->toBe([
            ['day_of_week' => 0, 'start_time' => '06:00:00', 'end_time' => '10:00:00'],
            ['day_of_week' => 0, 'start_time' => '18:00:00', 'end_time' => '20:00:00'],
            ['day_of_week' => 1, 'start_time' => '06:00:00', 'end_time' => '10:00:00'],
            ['day_of_week' => 1, 'start_time' => '18:00:00', 'end_time' => '20:00:00'],
        ]);
});

test('bare hour slots are normalised and a zero ticket limit becomes unlimited', function (): void {
    $this->artisan('migrator:trainers')->assertSuccessful();

    expect(Trainer::query()->findOrFail(26)->max_tickets_per_hour)->toBeNull()
        ->and(DB::table('schedules')->where('schedulable_id', 26)->first(['day_of_week', 'start_time', 'end_time']))
        ->toEqual((object) ['day_of_week' => 5, 'start_time' => '06:00:00', 'end_time' => '08:00:00']);
});

test('trainers of barns that were not migrated are skipped', function (): void {
    $this->artisan('migrator:trainers')
        ->expectsOutputToContain('Trainer #27: barn #999 was not migrated')
        ->assertSuccessful();

    expect(Trainer::withTrashed()->find(27))->toBeNull();
});

test('a trainer is linked to its legacy manager assignment', function (): void {
    $this->artisan('migrator:trainers')->assertSuccessful();

    expect($this->manager->centerAssignment(CenterFactory::new()->make(['id' => 6]))->trainer_id)->toBe(25);
});

test('re-running does not duplicate trainers or schedules', function (): void {
    $this->artisan('migrator:trainers')->assertSuccessful();
    $this->artisan('migrator:trainers')->assertSuccessful();

    expect(DB::table('trainers')->count())->toBe(2)
        ->and(DB::table('schedules')->count())->toBe(5);
});
