<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\CenterUserRole;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\SMSService;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->smsService = Mockery::mock(SMSService::class);
    $this->app->instance(SMSService::class, $this->smsService);

    \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'Default Test City', 'ar' => 'المدينة الافتراضية'],
    ]);

    $this->devPhone = '+966500000999';
    $this->devCode = '123456';
    config()->set('otp.dev.enabled', true);
    config()->set('otp.dev.phones', [$this->devPhone]);
    config()->set('otp.dev.code', $this->devCode);
});

$createRegisteredUser = function (string $phone, string $password = 'secret1234'): array {
    $user = User::create([
        'name' => 'Registered Test User',
        'phone' => $phone,
        'password' => Hash::make($password),
        'phone_verified_at' => null,
    ]);

    $center = Center::create([
        'city_id' => \Modules\Support\Models\City::query()->value('id'),
        'name' => 'User Registered Center',
        'slug' => 'user-registered-center-' . $user->id,
        'description' => 'A center created by this user.',
        'contact_phone' => '+966110000001',
        'status' => CenterStatus::INVISIBLE,
    ]);

    $center->users()->attach($user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($center->id);
    \Spatie\Permission\Models\Role::findOrCreate(CenterUserRole::Owner->value, 'center_user');
    $user->assignRole(CenterUserRole::Owner->value);

    return compact('user', 'center');
};

$createSecondaryUser = function (string $phone, Center $center, string $password = 'staffpass123'): User {
    $user = User::create([
        'name' => 'Secondary Staff',
        'phone' => $phone,
        'password' => Hash::make($password),
        'phone_verified_at' => now(),
    ]);

    $center->users()->attach($user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);

    return $user;
};

test('login phone returns 422 when phone field is missing', function (): void {
    $response = $this->postJson('/api/v1/centers/auth/phone', []);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone']);
});

test('login phone returns 422 with exists error when phone is not registered', function (): void {
    $response = $this->postJson('/api/v1/centers/auth/phone', [
        'phone' => '+966509999111',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone']);
    $errors = $response->json('errors');
    $this->assertNotEmpty($errors['phone']);
});

test('login phone returns 200 with message and expires_in_seconds when phone is registered', function () use ($createRegisteredUser): void {
    $createRegisteredUser($this->devPhone);

    $this->smsService->shouldReceive('sendOTP')->never();

    $response = $this->postJson('/api/v1/centers/auth/phone', [
        'phone' => $this->devPhone,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['message', 'expires_in_seconds']);
    $response->assertJsonMissingPath('token');
    $response->assertJsonMissingPath('user');
});

test('verify returns 422 when required fields are missing', function (): void {
    $response = $this->postJson('/api/v1/centers/auth/phone/verify', []);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone', 'code']);
});

test('verify returns 422 with code error when OTP code is wrong', function () use ($createRegisteredUser): void {
    $createRegisteredUser($this->devPhone);

    $response = $this->postJson('/api/v1/centers/auth/phone/verify', [
        'phone' => $this->devPhone,
        'code' => '000000',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
});

test('verify succeeds with correct dev OTP returns user token token_type', function () use ($createRegisteredUser): void {
    $data = $createRegisteredUser($this->devPhone, 'secret1234');
    $originalUserId = $data['user']->id;

    $data['user']->forceFill(['phone_verified_at' => null])->save();
    $data['user']->refresh();
    $this->assertNull($data['user']->phone_verified_at);

    $response = $this->postJson('/api/v1/centers/auth/phone/verify', [
        'phone' => $this->devPhone,
        'code' => $this->devCode,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user' => ['id', 'name', 'phone'],
        'token',
        'token_type',
    ]);

    $this->assertSame($originalUserId, $response->json('user.id'));
    $this->assertSame($this->devPhone, $response->json('user.phone'));
    $this->assertSame('Bearer', $response->json('token_type'));
    $this->assertIsString($response->json('token'));
    $this->assertNotEmpty($response->json('token'));
    $response->assertJsonMissingPath('center');

    $this->assertNotNull(
        User::find($originalUserId)->phone_verified_at,
        'phone_verified_at should have been set after successful OTP verify'
    );
});

test('verify returns 422 with phone exists error for unregistered phone even if code is dev format', function (): void {
    $response = $this->postJson('/api/v1/centers/auth/phone/verify', [
        'phone' => '+966508888777',
        'code' => $this->devCode,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone']);
});

test('index requires authentication returns 401', function (): void {
    $this->getJson('/api/v1/centers')->assertStatus(401);
});

test('index returns list of assigned centers with spatie role owner', function () use ($createRegisteredUser): void {
    $data = $createRegisteredUser($this->devPhone, 'secret');
    $user = $data['user'];
    $center = $data['center'];

    $response = $this->actingAs($user, 'center_user')
        ->getJson('/api/v1/centers');

    $response->assertStatus(200);
    $items = $response->json('data');
    $this->assertCount(1, $items);
    $this->assertSame($center->id, $items[0]['id']);
    $this->assertSame($center->name, $items[0]['name']);
    $this->assertSame(CenterUserRole::Owner->value, $items[0]['role']);
});

test('show returns 403 when user has no assignment', function () use ($createRegisteredUser): void {
    $otherPhone = '+966500000001';
    $data1 = $createRegisteredUser($this->devPhone);
    $data2 = $createRegisteredUser($otherPhone);

    $response = $this->actingAs($data2['user'], 'center_user')
        ->getJson('/api/v1/centers/' . $data1['center']->id);

    $response->assertStatus(403);
});

test('show returns 200 with CenterResource when user has assignment', function () use ($createRegisteredUser): void {
    $data = $createRegisteredUser($this->devPhone);

    $response = $this->actingAs($data['user'], 'center_user')
        ->getJson('/api/v1/centers/' . $data['center']->id);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['id', 'name', 'slug', 'contact_phone', 'status'],
    ]);
    $this->assertSame($data['center']->id, $response->json('data.id'));
});

test('access for primary user without password returns 422', function () use ($createRegisteredUser): void {
    $data = $createRegisteredUser($this->devPhone, 'password123');

    $response = $this->actingAs($data['user'], 'center_user')
        ->postJson('/api/v1/centers/' . $data['center']->id . '/access', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('access for primary user with wrong password returns 422', function () use ($createRegisteredUser): void {
    $data = $createRegisteredUser($this->devPhone, 'correct-password');

    $response = $this->actingAs($data['user'], 'center_user')
        ->postJson('/api/v1/centers/' . $data['center']->id . '/access', [
            'password' => 'wrong-password',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('access for primary user with correct password returns token and center', function () use ($createRegisteredUser): void {
    $data = $createRegisteredUser($this->devPhone, 'correct-password');

    $response = $this->actingAs($data['user'], 'center_user')
        ->postJson('/api/v1/centers/' . $data['center']->id . '/access', [
            'password' => 'correct-password',
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'token',
        'token_type',
        'center' => ['id', 'name', 'slug'],
    ]);
    $this->assertSame('Bearer', $response->json('token_type'));
    $this->assertSame($data['center']->id, $response->json('center.id'));
    $this->assertIsString($response->json('token'));
    $this->assertNotEmpty($response->json('token'));
});

test('access for non-primary user without password returns 200 same shape', function () use ($createRegisteredUser, $createSecondaryUser): void {
    $primary = $createRegisteredUser($this->devPhone, 'primary-password');
    $staffPhone = '+966500000002';
    $staff = $createSecondaryUser($staffPhone, $primary['center'], 'staff-password');

    $response = $this->actingAs($staff, 'center_user')
        ->postJson('/api/v1/centers/' . $primary['center']->id . '/access', []);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'token',
        'token_type',
        'center' => ['id', 'name', 'slug'],
    ]);
    $this->assertSame('Bearer', $response->json('token_type'));
    $this->assertSame($primary['center']->id, $response->json('center.id'));
    $this->assertIsString($response->json('token'));
    $this->assertNotEmpty($response->json('token'));
});

test('access returns 403 when user is not a member of the center', function () use ($createRegisteredUser): void {
    $data1 = $createRegisteredUser($this->devPhone);
    $data2 = $createRegisteredUser('+966500000003');

    $response = $this->actingAs($data2['user'], 'center_user')
        ->postJson('/api/v1/centers/' . $data1['center']->id . '/access', [
            'password' => 'whatever',
        ]);

    $response->assertStatus(403);
});

test('send verify endpoint rate limit distinction wrong code returns 422 too many wrong codes returns 429', function () use ($createRegisteredUser): void {
    $createRegisteredUser($this->devPhone);

    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/v1/centers/auth/phone/verify', [
            'phone' => $this->devPhone,
            'code' => '999999',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    $response6 = $this->postJson('/api/v1/centers/auth/phone/verify', [
        'phone' => $this->devPhone,
        'code' => '999999',
    ]);
    $response6->assertStatus(429);
    $response6->assertHeader('Retry-After');
});
