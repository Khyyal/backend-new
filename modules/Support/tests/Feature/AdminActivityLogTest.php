<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->url = '/api/v1/admin/activity-logs';
});

test('the four audited models write a log on create and on update', function (): void {
    $service = ServiceFactory::new()->create();
    $reservation = ReservationFactory::new()->create();
    $package = ClientPackageFactory::new()->create();
    $membership = ClientMembershipFactory::new()->create();

    foreach ([$service, $reservation, $package, $membership] as $model) {
        expect(Activity::query()->forSubject($model)->forEvent('created')->count())->toBe(1);
    }

    $package->update(['remaining_sessions' => $package->remaining_sessions - 1]);
    $logs = Activity::query()->forSubject($package)->forEvent('updated')->get();

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->log_name)->toBe('client_package');

    $package->save();
    expect(Activity::query()->forSubject($package)->forEvent('updated')->count())->toBe(1);
});

test('only admins with the permission can read the logs', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')
        ->getJson($this->url)->assertForbidden();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyActivityLogs])->create(), 'system_user')
        ->getJson($this->url)->assertOk();
});

test('logs can be filtered by record and shown individually, with the acting admin as causer', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $reservation = ReservationFactory::new()->create();
    $package = ClientPackageFactory::new()->create();

    $this->getJson($this->url.'?log_name=reservation&subject_id='.$reservation->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'created')
        ->assertJsonPath('data.0.causer.id', $this->admin->id);

    $id = Activity::query()->forSubject($package)->value('id');
    $this->getJson($this->url.'/'.$id)->assertOk()->assertJsonPath('data.log_name', 'client_package');
    $this->getJson($this->url.'/999999')->assertNotFound();
});

test('non-admin tokens cannot reach the logs', function (): void {
    $this->actingAs(ClientFactory::new()->create(), 'client')
        ->getJson($this->url)->assertUnauthorized();
});
