<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/LegacySchema.php';

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

    $schema->create('images', function (Blueprint $table): void {
        $table->id();
        $table->string('filename');
        $table->string('imageable_type')->nullable();
        $table->unsignedBigInteger('imageable_id')->nullable();
        $table->string('blur_hash')->nullable();
        $table->integer('order')->default(0);
        $table->timestamps();
    });

    $schema->create('barn_managers', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name');
        $table->string('phone_number');
        $table->string('password');
        $table->string('fcm_token')->nullable();
        $table->string('lang')->default('ar');
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

    createLegacyServiceTables();
    createLegacyBillingTables();
    createLegacyClientTables();

    $legacy = DB::connection('legacy');
    $at = '2024-10-01 10:00:00';

    $legacy->table('cities')->insert(['id' => 21, 'name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'lat' => 24.77, 'lng' => 46.73, 'radius' => 150, 'created_at' => $at, 'updated_at' => $at]);

    $legacy->table('barns')->insert([
        ['id' => 6, 'slug' => 'najd', 'name' => 'Najd', 'description' => null, 'city_id' => 21, 'location' => '{"lat":24.8260078,"lng":46.6575602}', 'zone' => 'Yasmin', 'active' => true, 'image' => '/storage/images/logo.png', 'contact_number' => '+966548791026', 'points' => 5, 'created_at' => $at, 'updated_at' => $at],
        ['id' => 7, 'slug' => 'rf-stud', 'name' => 'R&F', 'description' => null, 'city_id' => 99, 'location' => null, 'zone' => '', 'active' => false, 'image' => '/storage/images/gone.png', 'contact_number' => null, 'points' => 0, 'created_at' => $at, 'updated_at' => $at],
    ]);

    $legacy->table('barn_social_links')->insert([
        ['barn_id' => 6, 'type' => 'instagram', 'url' => 'https://instagram.com/najd', 'created_at' => $at, 'updated_at' => $at],
        ['barn_id' => 6, 'type' => 'myspace', 'url' => 'https://myspace.com/najd', 'created_at' => $at, 'updated_at' => $at],
    ]);

    Storage::fake('public');
    Storage::fake('s3');

    Http::preventStrayRequests();
    Http::fake([
        'https://app.khyyal.sa/storage/images/logo.png' => Http::response(UploadedFile::fake()->image('logo.png')->getContent()),
        'https://app.khyyal.sa/storage/images/gone.png' => Http::response('Not Found', 404),
    ]);

    foreach (['first.jpg', 'second.jpg', 'service.jpg'] as $filename) {
        Storage::disk('s3')->put("images/{$filename}", UploadedFile::fake()->image($filename)->getContent());
    }

    $legacy->table('images')->insert([
        ['id' => 11, 'filename' => 'second.jpg', 'imageable_type' => 'App\\Models\\Barn', 'imageable_id' => 6, 'blur_hash' => 'hash-2', 'order' => 2],
        ['id' => 12, 'filename' => 'first.jpg', 'imageable_type' => 'App\\Models\\Barn', 'imageable_id' => 6, 'blur_hash' => 'hash-1', 'order' => 1],
        ['id' => 13, 'filename' => 'missing.jpg', 'imageable_type' => 'App\\Models\\Barn', 'imageable_id' => 6, 'blur_hash' => null, 'order' => 3],
        ['id' => 14, 'filename' => 'service.jpg', 'imageable_type' => 'App\\Models\\Service', 'imageable_id' => 6, 'blur_hash' => null, 'order' => 0],
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
        ->and(DB::table('center_users')->count())->toBe(0)
        ->and(DB::table('media')->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('s3')->allFiles())->toEqualCanonicalizing(['images/first.jpg', 'images/second.jpg', 'images/service.jpg']);
});

test('barn gallery images become center images in their legacy order', function (): void {
    $this->artisan('migrator:centers')->assertSuccessful();

    $images = Center::query()->findOrFail(6)->getMedia('images');

    expect($images->map(fn ($media): array => [$media->file_name, $media->getCustomProperty('legacy_image_id'), $media->getCustomProperty('blur_hash')])->all())
        ->toBe([['first.jpg', 12, 'hash-1'], ['second.jpg', 11, 'hash-2']])
        ->and(Storage::disk('s3')->exists('images/first.jpg'))->toBeTrue()
        ->and(Storage::disk('s3')->exists('images/second.jpg'))->toBeTrue()
        ->and($images->pluck('disk')->unique()->all())->toBe(['s3']);
});

test('a missing gallery file is reported and the others still attach', function (): void {
    $this->artisan('migrator:centers')
        ->expectsOutputToContain('image #13 (images/missing.jpg) not found')
        ->assertSuccessful();

    expect(Center::query()->findOrFail(6)->getMedia('images'))->toHaveCount(2);
});

test('re-running does not duplicate gallery images', function (): void {
    $this->artisan('migrator:centers')->assertSuccessful();
    $this->artisan('migrator:centers')->assertSuccessful();

    expect(Center::query()->findOrFail(6)->getMedia('images'))->toHaveCount(2);
});

test('barn logos are downloaded from the old server to s3 as the center logo', function (): void {
    $this->artisan('migrator:centers')->assertSuccessful();

    $logos = Center::query()->findOrFail(6)->getMedia('logo');
    $logo = $logos->first();

    expect($logos)->toHaveCount(1)
        ->and($logo->disk)->toBe('s3')
        ->and($logo->getCustomProperty('legacy_path'))->toBe('/storage/images/logo.png')
        ->and($logo->file_name)->toBe('logo.png')
        ->and(Storage::disk('s3')->exists($logo->getPathRelativeToRoot()))->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://app.khyyal.sa/storage/images/logo.png');
});

test('a missing logo file is reported and the center keeps no logo', function (): void {
    $this->artisan('migrator:centers')
        ->expectsOutputToContain('logo https://app.khyyal.sa/storage/images/gone.png could not be downloaded (HTTP 404)')
        ->assertSuccessful();

    expect(Center::query()->findOrFail(7)->getMedia('logo'))->toBeEmpty();
});

test('re-running does not upload the logo again', function (): void {
    $this->artisan('migrator:centers')->assertSuccessful();
    $this->artisan('migrator:centers')->assertSuccessful();

    expect(DB::table('media')->where(['model_id' => 6, 'collection_name' => 'logo'])->count())->toBe(1);
});

test('manager push tokens from both legacy sources become devices of the kept center user', function (): void {
    $legacy = DB::connection('legacy');
    $legacy->table('barn_managers')->where('id', 4)->update(['fcm_token' => 'token-a']);
    $legacy->table('barn_managers')->where('id', 5)->update(['lang' => 'en']);
    $legacy->table('manager_fcm_tokens')->insert([
        ['manager_id' => 5, 'fcm_token' => 'token-a', 'updated_at' => '2025-01-01 00:00:00'],
        ['manager_id' => 5, 'fcm_token' => 'token-b', 'updated_at' => '2025-01-01 00:00:00'],
        ['manager_id' => 5, 'fcm_token' => null, 'updated_at' => '2025-01-01 00:00:00'],
    ]);

    $this->artisan('migrator:run')->assertSuccessful();
    $this->artisan('migrator:center-users')->assertSuccessful();

    expect(User::query()->findOrFail(5)->fcmTokensByLocale())->toEqualCanonicalizing(['en' => ['token-a', 'token-b']])
        ->and(DB::table('devices')->count())->toBe(2);
});
