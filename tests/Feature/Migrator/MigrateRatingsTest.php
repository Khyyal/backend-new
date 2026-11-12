<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Models\Center;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Clients\Models\Client;
use Modules\Support\Models\Rating;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    useLegacyServiceSchema();

    CenterFactory::new()->create(['id' => 6]);
    ClientFactory::new()->create(['id' => 2]);
    ClientFactory::new()->create(['id' => 3]);

    $rating = ['rateable_type' => 'App\\Models\\Reservation', 'rateable_id' => 1, 'comment' => null];

    DB::connection('legacy')->table('ratings')->insert([
        [...$rating, 'id' => 1, 'client_id' => 2, 'barn_id' => 6, 'stars' => 4, 'comment' => 'Great horses', 'created_at' => '2025-01-10 09:00:00', 'updated_at' => '2025-01-10 09:00:00'],
        [...$rating, 'id' => 2, 'client_id' => 3, 'barn_id' => 6, 'stars' => 2, 'comment' => 'Old one', 'created_at' => '2025-01-01 09:00:00', 'updated_at' => '2025-01-01 09:00:00'],
        [...$rating, 'id' => 3, 'client_id' => 3, 'barn_id' => 6, 'stars' => 5, 'comment' => 'Much better', 'created_at' => '2025-03-01 09:00:00', 'updated_at' => '2025-03-01 09:00:00', 'rateable_type' => 'App\\Models\\EventReservation'],
        [...$rating, 'id' => 4, 'client_id' => 2, 'barn_id' => 999, 'stars' => 3, 'created_at' => '2025-01-01 09:00:00', 'updated_at' => '2025-01-01 09:00:00'],
        [...$rating, 'id' => 5, 'client_id' => 888, 'barn_id' => 6, 'stars' => 3, 'created_at' => '2025-01-01 09:00:00', 'updated_at' => '2025-01-01 09:00:00'],
    ]);
});

test('legacy ratings land on the center with the migrated client as rater', function (): void {
    $this->artisan('migrator:ratings')->assertSuccessful();

    $rating = Rating::query()->where('rater_id', 2)->sole();

    expect($rating->rateable_type)->toBe((new Center)->getMorphClass())
        ->and($rating->rateable_id)->toBe(6)
        ->and($rating->rater_type)->toBe((new Client)->getMorphClass())
        ->and($rating->stars)->toBe(4)
        ->and($rating->comment)->toBe('Great horses')
        ->and($rating->created_at->toDateTimeString())->toBe('2025-01-10 09:00:00');
});

test('only the latest rating of a client for a center is kept', function (): void {
    $this->artisan('migrator:ratings')->assertSuccessful();

    $rating = Rating::query()->where('rater_id', 3)->sole();

    expect($rating->stars)->toBe(5)
        ->and($rating->comment)->toBe('Much better');
});

test('ratings of unmigrated centers or clients are skipped and re-running adds nothing', function (): void {
    $this->artisan('migrator:ratings')->assertSuccessful();
    $this->artisan('migrator:ratings')->assertSuccessful();

    expect(Rating::query()->count())->toBe(2);
});
