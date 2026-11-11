<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Models\Client;
use Modules\Support\Enums\Gender;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    useLegacyServiceSchema();

    CenterFactory::new()->create(['id' => 6]);
    $cityId = CenterFactory::new()->create()->city_id;

    $client = ['first_name' => null, 'last_name' => null, 'email' => null, 'phone_number' => null, 'sub' => null, 'gender' => 'unknown', 'image' => null, 'fcm_token' => null, 'device_token' => null, 'lang' => 'ar', 'city_id' => null, 'registered_barn_id' => null, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00', 'deleted_at' => null];

    DB::connection('legacy')->table('clients')->insert([
        [...$client, 'id' => 2, 'first_name' => 'Sadek', 'last_name' => ' ', 'phone_number' => '966555307591', 'gender' => 'male', 'city_id' => $cityId, 'registered_barn_id' => 6, 'fcm_token' => 'token-2', 'device_token' => 'DEVICE-UUID', 'lang' => 'en'],
        [...$client, 'id' => 3, 'phone_number' => '966500000003', 'city_id' => 999, 'fcm_token' => 'shared-token', 'updated_at' => '2025-02-01 00:00:00'],
        [...$client, 'id' => 4, 'phone_number' => '966500000004', 'fcm_token' => 'shared-token', 'updated_at' => '2025-03-01 00:00:00'],
        [...$client, 'id' => 10, 'phone_number' => '966500000010', 'fcm_token' => 'old-token', 'updated_at' => '2025-06-01 00:00:00', 'deleted_at' => '2025-06-01 00:00:00'],
        [...$client, 'id' => 11, 'phone_number' => '966500000010', 'first_name' => 'Kept', 'fcm_token' => 'new-token'],
        [...$client, 'id' => 20, 'sub' => 'apple-1', 'email' => 'a@privaterelay.appleid.com', 'fcm_token' => 'new-fcm-token'],
        [...$client, 'id' => 21, 'sub' => 'apple-2', 'phone_number' => ''],
    ]);
});

test('clients keep their ids with blank names as null, mapped gender and only migrated cities', function (): void {
    $this->artisan('migrator:clients')->assertSuccessful();

    $sadek = Client::query()->findOrFail(2);
    $other = Client::query()->findOrFail(3);

    expect($sadek->first_name)->toBe('Sadek')
        ->and($sadek->last_name)->toBeNull()
        ->and($sadek->phone_number)->toBe('966555307591')
        ->and($sadek->gender)->toBe(Gender::MALE->value)
        ->and($sadek->city_id)->not->toBeNull()
        ->and($other->gender)->toBe(Gender::OTHER->value)
        ->and($other->city_id)->toBeNull()
        ->and(DB::table('center_client')->where('center_id', 6)->pluck('client_id')->all())->toBe([2]);
});

test('clients without a phone are migrated separately with an empty phone', function (): void {
    $this->artisan('migrator:clients')
        ->expectsOutputToContain('2 clients have no phone number')
        ->assertSuccessful();

    expect(Client::query()->whereKey([20, 21])->pluck('phone_number')->all())->toBe(['', '']);
});

test('clients sharing a phone merge into the latest non-deleted one', function (): void {
    $this->artisan('migrator:clients')->assertSuccessful();

    expect(Client::withTrashed()->find(10))->toBeNull()
        ->and(Client::query()->findOrFail(11)->first_name)->toBe('Kept')
        ->and(DB::table('legacy_id_maps')->where('entity', 'client')->where('legacy_id', 10)->value('new_id'))->toBe(11)
        ->and(Client::query()->findOrFail(11)->deviceTokens()->sort()->values()->all())->toBe(['new-token', 'old-token']);
});

test('push tokens become devices with their locale and legacy device id', function (): void {
    $this->artisan('migrator:clients')->assertSuccessful();

    $device = Client::query()->findOrFail(2)->devices()->sole();

    expect($device->fcm_token)->toBe('token-2')
        ->and($device->device_identifier)->toBe('DEVICE-UUID')
        ->and($device->locale)->toBe('en')
        ->and(Client::query()->findOrFail(20)->devices()->count())->toBe(0);
});

test('a token shared by several clients goes to the one updated last', function (): void {
    $this->artisan('migrator:clients')->assertSuccessful();

    expect(Client::query()->findOrFail(3)->devices()->count())->toBe(0)
        ->and(Client::query()->findOrFail(4)->deviceTokens()->all())->toBe(['shared-token']);
});

test('re-running adds no clients, links or devices', function (): void {
    $this->artisan('migrator:clients')->assertSuccessful();
    $this->artisan('migrator:clients')->assertSuccessful();

    expect(DB::table('clients')->count())->toBe(6)
        ->and(DB::table('center_client')->count())->toBe(1)
        ->and(DB::table('devices')->count())->toBe(4);
});
