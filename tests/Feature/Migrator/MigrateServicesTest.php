<?php

use App\Console\Commands\Migrator\MigrateServicesCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Event;
use Modules\Services\Models\Membership;
use Modules\Services\Models\Service;
use Modules\Services\Models\TrainingService;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    useLegacyServiceSchema();
    Storage::fake('s3');
    Storage::fake('public');

    CenterFactory::new()->create(['id' => 6]);
    CenterFactory::new()->create(['id' => 8]);

    DB::table('trainers')->insert([
        ['id' => 25, 'center_id' => 6, 'name' => 'Mohammed'],
        ['id' => 30, 'center_id' => 8, 'name' => 'Elsewhere'],
    ]);

    $legacy = DB::connection('legacy');
    $at = '2025-01-01 10:00:00';

    $legacy->table('services')->insert([
        ['id' => 20, 'barn_id' => 6, 'service_type' => 'free_riding', 'durations_prices' => '[{"price":100,"duration":"30"},{"price":150,"duration":"60"}]', 'max_tickets_per_hour' => 5, 'max_tickets_per_day' => null, 'tickets_price' => null, 'days' => '["sunday","monday"]', 'hours' => '["11:00","12:00"]', 'visit_type' => 'allDay', 'name' => legacyName('جولة', 'Ride'), 'description' => legacyName('وصف'), 'status' => 'active', 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
        ['id' => 39, 'barn_id' => 6, 'service_type' => 'training', 'durations_prices' => '[{"price":80,"duration":"45"}]', 'max_tickets_per_hour' => null, 'max_tickets_per_day' => null, 'tickets_price' => null, 'days' => '[]', 'hours' => '[]', 'visit_type' => null, 'name' => legacyName('تدريب'), 'description' => '{"en":null}', 'status' => 'inactive', 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
        ['id' => 50, 'barn_id' => 6, 'service_type' => 'visit', 'durations_prices' => null, 'max_tickets_per_hour' => null, 'max_tickets_per_day' => 100, 'tickets_price' => 25, 'days' => '["friday"]', 'hours' => '[]', 'visit_type' => 'allDay', 'name' => legacyName('زيارة'), 'description' => legacyName('وصف'), 'status' => 'active', 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
        ['id' => 51, 'barn_id' => 6, 'service_type' => 'visit', 'durations_prices' => '[{"price":50,"duration":"5"}]', 'max_tickets_per_hour' => 5, 'max_tickets_per_day' => null, 'tickets_price' => null, 'days' => '["saturday"]', 'hours' => '["16:00","17:00"]', 'visit_type' => 'specificTime', 'name' => legacyName('زيارة'), 'description' => legacyName('وصف'), 'status' => 'active', 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
        ['id' => 60, 'barn_id' => 6, 'service_type' => 'resort', 'durations_prices' => null, 'max_tickets_per_hour' => null, 'max_tickets_per_day' => null, 'tickets_price' => null, 'days' => null, 'hours' => null, 'visit_type' => null, 'name' => legacyName('استراحة'), 'description' => legacyName('وصف'), 'status' => 'active', 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => '2025-06-01 00:00:00'],
    ]);

    $legacy->table('reservable_constraints')->insert([
        'constrainable_type' => 'App\\Models\\Service', 'constrainable_id' => 20, 'enable_pay_on_arrival' => true, 'enable_single_reservation' => null, 'enable_package_reservation' => false,
    ]);

    $legacy->table('resort_days')->insert([
        ['service_id' => 60, 'day' => 'sunday', 'price' => 300],
        ['service_id' => 60, 'day' => 'friday', 'price' => 450.5],
    ]);
    $legacy->table('resort_additional_services')->insert(['service_id' => 60, 'name' => 'Lunch', 'price' => 40]);
    $legacy->table('resort_services_settings')->insert(['service_id' => 60, 'close_all_free_riding' => true, 'close_all_training' => false, 'close_all_resort' => false, 'close_all_visit' => false]);

    $legacy->table('trainer_service')->insert([
        ['trainer_id' => 25, 'service_id' => 39],
        ['trainer_id' => 30, 'service_id' => 39],
    ]);

    $legacy->table('packages')->insert([
        ['id' => 11, 'service_id' => 39, 'duration' => 45, 'status' => 'active', 'created_at' => $at, 'updated_at' => $at],
        ['id' => 12, 'service_id' => 20, 'duration' => 30, 'status' => 'inactive', 'created_at' => $at, 'updated_at' => $at],
    ]);
    $legacy->table('package_sessions')->insert([
        ['id' => 101, 'package_id' => 11, 'count' => 5, 'price' => 250, 'duration' => 1],
        ['id' => 102, 'package_id' => 11, 'count' => 10, 'price' => 450, 'duration' => 2],
        ['id' => 103, 'package_id' => 12, 'count' => 4, 'price' => 300, 'duration' => 1],
    ]);

    $legacy->table('events')->insert([
        'id' => 2, 'barn_id' => 6, 'start_date' => '2025-03-12', 'end_date' => '2025-03-19', 'open_date' => null, 'close_date' => null, 'max_tickets' => 0, 'enter_type' => 'specific_date', 'name' => legacyName('مسابقة قفز'), 'description' => legacyName('وصف'), 'status' => 'active', 'created_at' => $at, 'updated_at' => $at,
    ]);
    $legacy->table('event_categories')->insert([
        ['event_id' => 2, 'name' => 'vip', 'price' => 500],
        ['event_id' => 2, 'name' => 'regular', 'price' => 50.25],
    ]);

    $legacy->table('horse_cares')->insert(['id' => 3, 'barn_id' => 6, 'name' => legacyName('عناية', 'Care'), 'description' => null, 'status' => 'active', 'created_at' => $at, 'updated_at' => $at]);
    $legacy->table('service_addons')->insert(['addonable_type' => 'App\\Models\\HorseCare', 'addonable_id' => 3, 'price' => 100, 'name' => 'Shoeing']);

    $legacy->table('memberships')->insert([
        'id' => 6, 'barn_id' => 6, 'visit_service_id' => 50, 'price' => 1000, 'duration' => 1, 'daily_invitation_limit' => 2, 'color' => 'C7B140', 'name' => legacyName('عضوية ذهبية', 'Gold'), 'description' => legacyName('وصف'), 'status' => 'active', 'created_at' => $at, 'updated_at' => $at,
    ]);
    $legacy->table('membership_packages')->insert([
        ['package_id' => 11, 'membership_id' => 6, 'session_id' => 101, 'trainer_id' => 25, 'deleted_at' => null],
        ['package_id' => 11, 'membership_id' => 6, 'session_id' => 102, 'trainer_id' => null, 'deleted_at' => $at],
    ]);

    Storage::disk('s3')->put('images/ride.jpg', UploadedFile::fake()->image('ride.jpg')->getContent());
    Storage::disk('s3')->put('images/event.jpg', UploadedFile::fake()->image('event.jpg')->getContent());
    $legacy->table('images')->insert([
        ['filename' => 'ride.jpg', 'imageable_type' => 'App\\Models\\Service', 'imageable_id' => 20, 'blur_hash' => null, 'order' => 0],
        ['filename' => 'event.jpg', 'imageable_type' => 'App\\Models\\Event', 'imageable_id' => 2, 'blur_hash' => null, 'order' => 0],
    ]);
});

/**
 * @return array<int, array<string, mixed>>
 */
function priceOptionsOf(int $serviceId): array
{
    return DB::table('price_options')->where('service_id', $serviceId)->orderBy('id')
        ->get(['name', 'price', 'quantity', 'unit'])->map(fn ($row): array => (array) $row)->all();
}

/**
 * @return array<string, string>
 */
function settingsOf(int $serviceId): array
{
    return DB::table('service_settings')->where('service_id', $serviceId)->pluck('value', 'key')->all();
}

test('riding services keep their id with minute options in halalas, schedules and booking settings', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $ride = Service::query()->findOrFail(20);

    expect($ride->type)->toBe(ServiceType::RecreationRiding)
        ->and($ride->getTranslation('name', 'en'))->toBe('Ride')
        ->and($ride->slug)->toBe('ride')
        ->and($ride->serviceable->max_tickets_per_hour)->toBe(5)
        ->and(priceOptionsOf(20))->toBe([
            ['name' => null, 'price' => 10000, 'quantity' => 30, 'unit' => PriceOptionUnit::MINUTE->value],
            ['name' => null, 'price' => 15000, 'quantity' => 60, 'unit' => PriceOptionUnit::MINUTE->value],
        ])
        ->and($ride->serviceable->schedules()->orderBy('day_of_week')->get(['day_of_week', 'start_time', 'end_time'])->toArray())->toBe([
            ['day_of_week' => 0, 'start_time' => '11:00:00', 'end_time' => '13:00:00'],
            ['day_of_week' => 1, 'start_time' => '11:00:00', 'end_time' => '13:00:00'],
        ])
        ->and(settingsOf(20))->toEqualCanonicalizing(['enable_cash_on_arrival' => '1', 'enable_single_session_booking' => '1', 'enable_package_booking' => '0']);
});

test('training services get only same-center trainers', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $training = Service::query()->findOrFail(39);

    expect($training->status->value)->toBe('inactive')
        ->and($training->serviceable)->toBeInstanceOf(TrainingService::class)
        ->and($training->serviceable->trainers()->pluck('trainers.id')->all())->toBe([25]);
});

test('every package tier becomes a service package with its own price option', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $packages = DB::table('service_packages')->where('service_id', 39)->orderBy('id')->get();
    $options = DB::table('price_options')->where('service_id', 39)->orderBy('id')->get();

    expect($packages->map(fn ($package): array => [$package->count, $package->price, $package->duration])->all())->toBe([[5, 25000, 1], [10, 45000, 2]])
        ->and($options)->toHaveCount(2)
        ->and($packages->pluck('price_option_id')->all())->toBe($options->pluck('id')->all())
        ->and($options->pluck('quantity')->unique()->all())->toBe([45])
        ->and(DB::table('legacy_id_maps')->where('entity', MigrateServicesCommand::ENTITY_SERVICE_PACKAGE)->whereIn('legacy_id', [101, 102])->pluck('new_id', 'legacy_id')->all())
        ->toBe([101 => $packages[0]->id, 102 => $packages[1]->id]);
});

test('package tiers keep the legacy package status', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    expect(DB::table('service_packages')->where('service_id', 39)->pluck('status')->unique()->all())->toBe(['active'])
        ->and(DB::table('service_packages')->where('service_id', 20)->whereNull('deleted_at')->pluck('status')->all())->toBe(['inactive']);
});

test('all-day and specific-time visits get one ticket price and matching schedules', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $allDay = Service::query()->findOrFail(50)->serviceable;
    $specific = Service::query()->findOrFail(51)->serviceable;

    expect($allDay->enter_type->value)->toBe('all_day')
        ->and($allDay->max_tickets_per_day)->toBe(100)
        ->and(priceOptionsOf(50))->toBe([['name' => null, 'price' => 2500, 'quantity' => null, 'unit' => 'option']])
        ->and($allDay->schedules()->get(['day_of_week', 'start_time', 'end_time'])->toArray())->toBe([['day_of_week' => 5, 'start_time' => null, 'end_time' => null]])
        ->and($specific->enter_type->value)->toBe('specific_time')
        ->and(priceOptionsOf(51))->toBe([['name' => null, 'price' => 5000, 'quantity' => null, 'unit' => 'option']])
        ->and($specific->schedules()->get(['day_of_week', 'start_time', 'end_time'])->toArray())->toBe([['day_of_week' => 6, 'start_time' => '16:00:00', 'end_time' => '18:00:00']]);
});

test('resorts get day prices, addons and close flags, keeping their soft delete', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    expect(Service::query()->find(60))->toBeNull()
        ->and(Service::withTrashed()->findOrFail(60)->type)->toBe(ServiceType::Resort)
        ->and(priceOptionsOf(60))->toBe([
            ['name' => null, 'price' => 30000, 'quantity' => 0, 'unit' => 'option'],
            ['name' => null, 'price' => 45050, 'quantity' => 5, 'unit' => 'option'],
            ['name' => 'Lunch', 'price' => 4000, 'quantity' => null, 'unit' => 'option'],
        ])
        ->and(settingsOf(60))->toMatchArray([
            'close_all_recreational_ride_bookings' => '1',
            'close_all_training_bookings' => '0',
            'close_all_visit_bookings' => '0',
            'close_all_resort_bookings' => '0',
        ]);
});

test('events become services with ticket categories, sales window fallback and an id map', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $serviceId = DB::table('legacy_id_maps')->where(['entity' => MigrateServicesCommand::ENTITY_EVENT, 'legacy_id' => 2])->value('new_id');
    $service = Service::query()->findOrFail($serviceId);
    $event = $service->serviceable;

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->id)->toBe(2)
        ->and($event->occurrence_type->value)->toBe('specific_day')
        ->and($event->open_date->toDateString())->toBe('2025-03-12')
        ->and($event->close_date->toDateString())->toBe('2025-03-19')
        ->and($event->max_tickets_per_day)->toBe(1)
        ->and(priceOptionsOf($serviceId))->toBe([
            ['name' => 'vip', 'price' => 50000, 'quantity' => null, 'unit' => 'option'],
            ['name' => 'regular', 'price' => 5025, 'quantity' => null, 'unit' => 'option'],
        ]);
});

test('horse care addons become named price options', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $serviceId = DB::table('legacy_id_maps')->where(['entity' => MigrateServicesCommand::ENTITY_HORSE_CARE, 'legacy_id' => 3])->value('new_id');

    expect(Service::query()->findOrFail($serviceId)->type)->toBe(ServiceType::HorseCare)
        ->and(priceOptionsOf($serviceId))->toBe([['name' => 'Shoeing', 'price' => 10000, 'quantity' => null, 'unit' => 'option']]);
});

test('memberships keep their visit service and active package tiers', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $serviceId = DB::table('legacy_id_maps')->where(['entity' => MigrateServicesCommand::ENTITY_MEMBERSHIP, 'legacy_id' => 6])->value('new_id');
    $membership = Service::query()->findOrFail($serviceId)->serviceable;
    $tier = DB::table('legacy_id_maps')->where(['entity' => MigrateServicesCommand::ENTITY_SERVICE_PACKAGE, 'legacy_id' => 101])->value('new_id');

    expect($membership)->toBeInstanceOf(Membership::class)
        ->and($membership->visit_service_id)->toBe(50)
        ->and($membership->max_visits_per_day)->toBe(2)
        ->and($membership->color)->toBe('#C7B140')
        ->and(priceOptionsOf($serviceId))->toBe([['name' => null, 'price' => 100000, 'quantity' => null, 'unit' => 'option']])
        ->and(DB::table('membership_packages')->get(['membership_id', 'service_package_id', 'trainer_id'])->map(fn ($row): array => (array) $row)->all())
        ->toBe([['membership_id' => 6, 'service_package_id' => $tier, 'trainer_id' => 25]]);
});

test('service and event gallery images are copied to s3', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $eventServiceId = DB::table('legacy_id_maps')->where(['entity' => MigrateServicesCommand::ENTITY_EVENT, 'legacy_id' => 2])->value('new_id');

    expect(Service::query()->findOrFail(20)->getMedia('images')->pluck('file_name')->all())->toBe(['ride.jpg'])
        ->and(Service::query()->findOrFail($eventServiceId)->getMedia('images')->pluck('disk')->all())->toBe(['s3']);
});

test('re-running creates nothing new', function (): void {
    $this->artisan('migrator:services')->assertSuccessful();

    $counts = fn (): array => collect(['services', 'price_options', 'service_packages', 'schedules', 'service_settings', 'membership_packages', 'trainer_training_service', 'media'])
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();

    $before = $counts();
    $this->artisan('migrator:services')->assertSuccessful();

    expect($counts())->toBe($before);
});

test('a dry run writes nothing', function (): void {
    $this->artisan('migrator:services', ['--dry-run' => true])->assertSuccessful();

    expect(DB::table('services')->count())->toBe(0)
        ->and(DB::table('media')->count())->toBe(0);
});
