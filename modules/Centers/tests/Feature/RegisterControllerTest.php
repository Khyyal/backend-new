<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\CenterUserRole;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\SMSService;
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

$validPayload = fn (): array => [
    'name' => 'Phoenix Learning Center',
    'description' => 'A top-tier tutoring center for all ages.',
    'contact_phone' => '+966112345678',
    'user' => [
        'name' => 'Ahmad Al-Farsi',
        'phone' => '+966500000999',
        'code' => '123456',
    ],
];

test('register returns 422 when top-level required fields are missing', function () use ($validPayload): void {
    $response = $this->postJson('/api/v1/centers/register', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'name',
        'contact_phone',
        'user.name',
        'user.phone',
        'user.code',
    ]);
});

test('register returns 422 when name exceeds 255 characters', function () use ($validPayload): void {
    $payload = $validPayload();
    $payload['name'] = str_repeat('x', 256);

    $response = $this->postJson('/api/v1/centers/register', $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('name');
});

test('register returns 422 when contact_phone exceeds 32 characters', function () use ($validPayload): void {
    $payload = $validPayload();
    $payload['contact_phone'] = str_repeat('1', 33);

    $response = $this->postJson('/api/v1/centers/register', $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('contact_phone');
});

test('register returns 422 when user.name exceeds 255 characters', function () use ($validPayload): void {
    $payload = $validPayload();
    $payload['user']['name'] = str_repeat('x', 256);

    $response = $this->postJson('/api/v1/centers/register', $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('user.name');
});

test('register returns 422 when user.phone exceeds 32 characters', function () use ($validPayload): void {
    $payload = $validPayload();
    $payload['user']['phone'] = str_repeat('1', 33);

    $response = $this->postJson('/api/v1/centers/register', $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('user.phone');
});

test('register returns 422 with user.code error for wrong otp code', function () use ($validPayload): void {
    $payload = $validPayload();
    $payload['user']['code'] = '000000';

    $response = $this->postJson('/api/v1/centers/register', $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('user.code');

    $this->assertDatabaseMissing('center_users', [
        'phone' => $this->devPhone,
    ]);
    $this->assertDatabaseMissing('centers', [
        'contact_phone' => $payload['contact_phone'],
    ]);
});

test('register does not persist any record when otp is invalid (transaction rollback)', function (): void {
    $uniquePhone = '+966500000123';
    $uniqueContact = '+966110000123';
    $centerName = 'Rollback Test Center';
    $userName = 'Rollback User';

    $response = $this->postJson('/api/v1/centers/register', [
        'name' => $centerName,
        'description' => 'Should never be saved',
        'contact_phone' => $uniqueContact,
        'user' => [
            'name' => $userName,
            'phone' => $uniquePhone,
            'code' => '0000',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('user.code');

    $this->assertDatabaseMissing('center_users', ['phone' => $uniquePhone]);
    $this->assertDatabaseMissing('center_users', ['name' => $userName]);
    $this->assertDatabaseMissing('centers', ['name' => $centerName]);
    $this->assertDatabaseMissing('centers', ['contact_phone' => $uniqueContact]);
    $this->assertDatabaseMissing('center_user_assignment', ['is_primary' => true]);
});

test('register succeeds with valid payload using dev otp', function () use ($validPayload): void {
    $payload = $validPayload();

    $response = $this->postJson('/api/v1/centers/register', $payload);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'user' => [
            'id',
            'name',
            'phone',
        ],
        'center' => [
            'id',
            'name',
            'slug',
            'description',
            'contact_phone',
            'status',
        ],
        'token',
    ]);

    expect($response->json('user.name'))->toBe($payload['user']['name']);
    expect($response->json('user.phone'))->toBe($payload['user']['phone']);
    $userId = $response->json('user.id');
    $this->assertNotNull(\Modules\Centers\Models\User::find($userId)->phone_verified_at);

    expect($response->json('center.name'))->toBe($payload['name']);
    expect($response->json('center.description'))->toBe($payload['description']);
    expect($response->json('center.contact_phone'))->toBe($payload['contact_phone']);
    expect($response->json('center.status'))->toBe(CenterStatus::INVISIBLE->value);
    expect($response->json('center.slug'))->toBeString();

    expect($response->json('token'))->toBeString();
});

test('register persists correct db state for user, center and pivot', function () use ($validPayload): void {
    $payload = $validPayload();

    $response = $this->postJson('/api/v1/centers/register', $payload);
    $response->assertStatus(201);

    $userId = $response->json('user.id');
    $centerId = $response->json('center.id');

    $this->assertDatabaseHas('center_users', [
        'id' => $userId,
        'name' => $payload['user']['name'],
        'phone' => $payload['user']['phone'],
    ]);

    $this->assertNotNull(
        \Modules\Centers\Models\User::find($userId)->phone_verified_at
    );

    $this->assertDatabaseHas('centers', [
        'id' => $centerId,
        'name' => $payload['name'],
        'description' => $payload['description'],
        'contact_phone' => $payload['contact_phone'],
        'status' => CenterStatus::INVISIBLE->value,
    ]);

    $this->assertDatabaseHas('center_user_assignment', [
        'center_id' => $centerId,
        'user_id' => $userId,
        'status' => ActivationStatus::ACTIVE->value,
        'is_primary' => true,
    ]);
});

test('register assigns owner role to primary user within center', function () use ($validPayload): void {
    $payload = $validPayload();
    $payload['user']['name'] = 'Role Owner Test';

    $response = $this->postJson('/api/v1/centers/register', $payload);
    $response->assertStatus(201);

    $userId = $response->json('user.id');
    $centerId = $response->json('center.id');

    $user = \Modules\Centers\Models\User::find($userId);
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($centerId);

    expect($user->hasRole(CenterUserRole::Owner->value))->toBeTrue();
});

test('register auto-generates unique slug from center name', function (): void {
    $name = 'Slug Test Center Alpha';

    $response = $this->postJson('/api/v1/centers/register', [
        'name' => $name,
        'contact_phone' => '+966111111222',
        'user' => [
            'name' => 'Slug User 1',
            'phone' => $this->devPhone,
            'code' => $this->devCode,
        ],
    ]);

    $response->assertStatus(201);
    expect($response->json('center.slug'))->toBe(\Illuminate\Support\Str::slug($name));

    $secondPhone = '+966500000222';
    \Modules\Support\Database\Factories\OTPFactory::new()->create([
        'phone_number' => $secondPhone,
        'code' => \Illuminate\Support\Facades\Hash::make('2222'),
        'expires_at' => now()->addMinutes(5),
        'verified_at' => null,
    ]);

    $response2 = $this->postJson('/api/v1/centers/register', [
        'name' => $name,
        'contact_phone' => '+966111111333',
        'user' => [
            'name' => 'Slug User 2',
            'phone' => $secondPhone,
            'code' => '2222',
        ],
    ]);

    $response2->assertStatus(201);
    $secondSlug = $response2->json('center.slug');
    expect($secondSlug)->toStartWith(\Illuminate\Support\Str::slug($name) . '-');
});

test('register works without description field (nullable)', function (): void {
    $response = $this->postJson('/api/v1/centers/register', [
        'name' => 'Minimal Center',
        'contact_phone' => '+966117777888',
        'user' => [
            'name' => 'Minimal User',
            'phone' => $this->devPhone,
            'code' => $this->devCode,
        ],
    ]);

    $response->assertStatus(201);
    expect($response->json('center.description'))->toBeNull();
});

test('register endpoint is accessible without authentication', function () use ($validPayload): void {
    $response = $this->postJson('/api/v1/centers/register', $validPayload());

    $response->assertStatus(201);
});

test('register endpoint returns 429 after exceeding throttle limit', function (): void {
    config()->set('otp.dev.enabled', false);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/centers/register', [])->assertStatus(422);
    }

    $response = $this->postJson('/api/v1/centers/register', []);
    $response->assertStatus(429);
});

test('register uses a non-conflicting phone for center user', function (): void {
    $newPhone = '+966509999111';

    \Modules\Support\Database\Factories\OTPFactory::new()->create([
        'phone_number' => $newPhone,
        'code' => \Illuminate\Support\Facades\Hash::make('9999'),
        'expires_at' => now()->addMinutes(5),
        'verified_at' => null,
    ]);

    $response = $this->postJson('/api/v1/centers/register', [
        'name' => 'Alt Phone Center',
        'contact_phone' => '+966112222333',
        'user' => [
            'name' => 'Alt User',
            'phone' => $newPhone,
            'code' => '9999',
        ],
    ]);

    $response->assertStatus(201);
    expect($response->json('user.phone'))->toBe($newPhone);
});

test('register joins user to center with today joined_at date', function () use ($validPayload): void {
    $response = $this->postJson('/api/v1/centers/register', $validPayload());
    $response->assertStatus(201);

    $assignment = \Illuminate\Support\Facades\DB::table('center_user_assignment')
        ->where('center_id', $response->json('center.id'))
        ->where('user_id', $response->json('user.id'))
        ->first();

    expect($assignment)->not->toBeNull();
    expect(str_starts_with((string) $assignment->joined_at, now()->toDateString()))->toBeTrue();
});
