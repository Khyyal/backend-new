<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');

    $schema = Schema::connection('legacy');

    $schema->create('cities', function (Blueprint $table): void {
        $table->id();
        $table->string('name_ar');
        $table->string('name_en');
        $table->decimal('lat', 10, 8)->nullable();
        $table->decimal('lng', 11, 8)->nullable();
        $table->integer('radius')->nullable();
        $table->timestamps();
    });

    $schema->create('barns', function (Blueprint $table): void {
        $table->id();
        $table->string('slug');
        $table->string('name');
        $table->text('description')->nullable();
        $table->unsignedBigInteger('city_id')->nullable();
        $table->string('location')->nullable();
        $table->string('zone')->nullable();
        $table->boolean('active')->default(false);
        $table->string('image')->nullable();
        $table->string('contact_number')->nullable();
        $table->integer('points')->default(0);
        $table->timestamps();
    });

    $schema->create('barn_social_links', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('barn_id');
        $table->string('type');
        $table->string('url');
        $table->timestamps();
    });

    $schema->create('barn_managers', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name');
        $table->string('phone_number');
        $table->string('password');
        $table->timestamps();
        $table->softDeletes();
    });

    $schema->create('barn_manager_assignments', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('barn_id');
        $table->unsignedBigInteger('barn_manager_id');
        $table->string('role');
        $table->timestamps();
    });

    $schema->create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('group');
        $table->timestamps();
    });

    $schema->create('manager_permissions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('barn_manager_id');
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('barn_id');
        $table->timestamps();
    });

    $legacy = DB::connection('legacy');
    $at = '2024-10-01 10:00:00';

    $legacy->table('cities')->insert(['id' => 21, 'name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'lat' => 24.77, 'lng' => 46.73, 'radius' => 150, 'created_at' => $at, 'updated_at' => $at]);

    $legacy->table('barns')->insert([
        ['id' => 6, 'slug' => 'najd', 'name' => 'Najd', 'description' => null, 'city_id' => 21, 'location' => '{"lat":24.8260078,"lng":46.6575602}', 'zone' => 'Yasmin', 'active' => true, 'image' => null, 'contact_number' => '+966548791026', 'points' => 5, 'created_at' => $at, 'updated_at' => $at],
        ['id' => 7, 'slug' => 'rf-stud', 'name' => 'R&F', 'description' => null, 'city_id' => 99, 'location' => null, 'zone' => '', 'active' => false, 'image' => null, 'contact_number' => null, 'points' => 0, 'created_at' => $at, 'updated_at' => $at],
    ]);

    $legacy->table('barn_social_links')->insert([
        ['barn_id' => 6, 'type' => 'instagram', 'url' => 'https://instagram.com/najd', 'created_at' => $at, 'updated_at' => $at],
        ['barn_id' => 6, 'type' => 'myspace', 'url' => 'https://myspace.com/najd', 'created_at' => $at, 'updated_at' => $at],
    ]);

    $legacy->table('barn_managers')->insert([
        ['id' => 2, 'full_name' => 'Owner', 'phone_number' => '966500000001', 'password' => Hash::make('old-secret'), 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
        ['id' => 3, 'full_name' => 'Manager', 'phone_number' => '966500000003', 'password' => Hash::make('x'), 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
        ['id' => 4, 'full_name' => 'Old duplicate', 'phone_number' => '966500000004', 'password' => Hash::make('x'), 'created_at' => $at, 'updated_at' => '2024-12-01 00:00:00', 'deleted_at' => '2024-12-01 00:00:00'],
        ['id' => 5, 'full_name' => 'Current duplicate', 'phone_number' => '966500000004', 'password' => Hash::make('x'), 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
        ['id' => 9, 'full_name' => 'Never assigned', 'phone_number' => '966500000009', 'password' => Hash::make('x'), 'created_at' => $at, 'updated_at' => $at, 'deleted_at' => null],
    ]);

    $legacy->table('barn_manager_assignments')->insert([
        ['barn_id' => 6, 'barn_manager_id' => 2, 'role' => 'owner', 'created_at' => $at, 'updated_at' => $at],
        ['barn_id' => 6, 'barn_manager_id' => 3, 'role' => 'manager', 'created_at' => $at, 'updated_at' => $at],
        ['barn_id' => 7, 'barn_manager_id' => 4, 'role' => 'owner', 'created_at' => $at, 'updated_at' => $at],
        ['barn_id' => 7, 'barn_manager_id' => 5, 'role' => 'manager', 'created_at' => $at, 'updated_at' => $at],
    ]);

    $legacy->table('permissions')->insert([
        ['id' => 24, 'name' => 'can_edit', 'group' => 'other'],
        ['id' => 28, 'name' => 'free_riding', 'group' => 'services_management'],
        ['id' => 99, 'name' => 'mystery', 'group' => 'other'],
    ]);

    $legacy->table('manager_permissions')->insert([
        ['barn_manager_id' => 3, 'permission_id' => 24, 'barn_id' => 6],
        ['barn_manager_id' => 3, 'permission_id' => 28, 'barn_id' => 6],
        ['barn_manager_id' => 3, 'permission_id' => 99, 'barn_id' => 6],
    ]);
});

test('barns become centers with their legacy ids, location and visibility', function (): void {
    $this->artisan('migrator:run')->assertSuccessful();

    $najd = Center::query()->findOrFail(6);
    expect($najd->status)->toBe(CenterStatus::VISIBLE)
        ->and($najd->state)->toBe(CenterState::Active)
        ->and((float) $najd->lat)->toBe(24.8260078)
        ->and((float) $najd->lng)->toBe(46.6575602)
        ->and($najd->city_id)->toBe(21)
        ->and($najd->address)->toBe('Yasmin')
        ->and($najd->contact_phone)->toBe('+966548791026')
        ->and($najd->social_links)->toBe(['instagram' => 'https://instagram.com/najd']);

    $stud = Center::query()->findOrFail(7);
    expect($stud->status)->toBe(CenterStatus::INVISIBLE)
        ->and($stud->city_id)->toBeNull()
        ->and($stud->lat)->toBeNull()
        ->and($stud->address)->toBeNull();
});

test('owners become primary users who keep their phone and password', function (): void {
    $this->artisan('migrator:run')->assertSuccessful();

    $owner = User::query()->findOrFail(2);
    $najd = Center::query()->findOrFail(6);

    app(PermissionRegistrar::class)->setPermissionsTeamId(6);

    expect($owner->phone)->toBe('966500000001')
        ->and(Hash::check('old-secret', $owner->password))->toBeTrue()
        ->and($owner->isPrimaryOf($najd))->toBeTrue()
        ->and($owner->hasRole('owner'))->toBeTrue();
});

test('managers get their mapped legacy permissions on that center only', function (): void {
    $this->artisan('migrator:run')->assertSuccessful();

    $manager = User::query()->findOrFail(3);
    $najd = Center::query()->findOrFail(6);

    expect($manager->isPrimaryOf($najd))->toBeFalse()
        ->and($manager->centerPermissionNames($najd))->toEqualCanonicalizing([
            CenterUserPermission::EditReservations->value,
            CenterUserPermission::ManageRecreationRidingServices->value,
        ])
        ->and($manager->hasCenterPermission($najd, CenterUserPermission::ManageUsers))->toBeFalse();
});

test('managers sharing a phone are merged into the active one', function (): void {
    $this->artisan('migrator:run')->assertSuccessful();

    expect(User::withTrashed()->find(4))->toBeNull()
        ->and(DB::table('legacy_id_maps')->where(['entity' => 'center_user', 'legacy_id' => 4])->value('new_id'))->toBe(5);

    $merged = User::query()->findOrFail(5);

    expect($merged->isPrimaryOf(Center::query()->findOrFail(7)))->toBeTrue();
});

test('managers without an assignment are not migrated', function (): void {
    $this->artisan('migrator:run')->assertSuccessful();

    expect(User::withTrashed()->find(9))->toBeNull();
});

test('running the migrators twice yields the same data', function (): void {
    $this->artisan('migrator:run')->assertSuccessful();
    $this->artisan('migrator:run')->assertSuccessful();

    expect(DB::table('centers')->count())->toBe(2)
        ->and(DB::table('center_users')->count())->toBe(3)
        ->and(DB::table('center_user_assignment')->count())->toBe(3);
});

test('a dry run writes nothing', function (): void {
    $this->artisan('migrator:run', ['--dry-run' => true])->assertSuccessful();

    expect(DB::table('centers')->count())->toBe(0)
        ->and(DB::table('center_users')->count())->toBe(0);
});
