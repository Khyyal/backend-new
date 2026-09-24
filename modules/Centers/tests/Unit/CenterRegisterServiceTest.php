<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\CenterUserRole;
use Modules\Centers\Http\Requests\Center\RegisterCenterFormRequest;
use Modules\Centers\Services\CenterAuthService;
use Modules\Centers\Services\CenterRegisterService;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\OtpVerificationService;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'Unit Test City', 'ar' => 'مدينة الاختبار'],
    ]);

    $this->otp = Mockery::mock(OtpVerificationService::class);
    $this->auth = Mockery::mock(CenterAuthService::class);

    $this->service = new CenterRegisterService($this->otp, $this->auth);
});

$fakeRequest = function (array $overrides = []): RegisterCenterFormRequest {
    $data = array_merge([
        'name' => 'Unit Test Center',
        'description' => 'Created via service unit test',
        'contact_phone' => '+966110000001',
        'user' => [
            'name' => 'Unit Tester',
            'phone' => '+966509999001',
            'code' => '111111',
        ],
    ], $overrides);

    $request = RegisterCenterFormRequest::create('/register', 'POST', $data);
    $request->setContainer(app());
    $request->merge($data);

    return $request;
};

test('register verifies otp with correct phone and code', function () use ($fakeRequest): void {
    $phone = '+966509999001';
    $code = '111111';

    $this->otp
        ->shouldReceive('verify')
        ->once()
        ->with($phone, $code)
        ->andReturn(true);

    $this->auth
        ->shouldReceive('issueAppToken')
        ->once()
        ->andReturn('fake-token-xyz');

    $this->service->register($fakeRequest());
});

test('register throws validation exception on user.code when otp fails', function () use ($fakeRequest): void {
    $this->otp
        ->shouldReceive('verify')
        ->once()
        ->andReturn(false);

    $this->auth->shouldNotReceive('issueAppToken');

    try {
        $this->service->register($fakeRequest());
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('user.code');
    }
});

test('register does not create user or center when otp fails', function () use ($fakeRequest): void {
    $phone = '+966509999099';
    $centerName = 'Never Created Center';
    $contactPhone = '+966110000099';

    $this->otp
        ->shouldReceive('verify')
        ->once()
        ->andReturn(false);

    try {
        $this->service->register($fakeRequest([
            'name' => $centerName,
            'contact_phone' => $contactPhone,
            'user' => [
                'name' => 'Never Created User',
                'phone' => $phone,
                'code' => '0000',
            ],
        ]));
    } catch (ValidationException $e) {
    }

    $this->assertDatabaseMissing('center_users', ['phone' => $phone]);
    $this->assertDatabaseMissing('centers', ['name' => $centerName]);
    $this->assertDatabaseMissing('centers', ['contact_phone' => $contactPhone]);
});

test('register returns user, center and token for valid request', function () use ($fakeRequest): void {
    $phone = '+966509999002';
    $userName = 'Success User';
    $centerName = 'Success Center';
    $contactPhone = '+966110000002';
    $description = 'Great success';
    $token = 'srv-fake-token-123';

    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth
        ->shouldReceive('issueAppToken')
        ->once()
        ->withArgs(function ($user, $req) use ($userName) {
            return $user->name === $userName && $req instanceof RegisterCenterFormRequest;
        })
        ->andReturn($token);

    $result = $this->service->register($fakeRequest([
        'name' => $centerName,
        'description' => $description,
        'contact_phone' => $contactPhone,
        'user' => [
            'name' => $userName,
            'phone' => $phone,
            'code' => '123',
        ],
    ]));

    expect($result)->toHaveKeys(['user', 'center', 'token']);
    expect($result['token'])->toBe($token);

    $user = $result['user'];
    expect($user->name)->toBe($userName);
    expect($user->phone)->toBe($phone);
    expect($user->phone_verified_at)->not->toBeNull();

    $center = $result['center'];
    expect($center->name)->toBe($centerName);
    expect($center->description)->toBe($description);
    expect($center->contact_phone)->toBe($contactPhone);
    expect($center->status)->toBe(CenterStatus::INVISIBLE);
    expect($center->slug)->toBeString();
});

test('register marks user phone as verified', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $result = $this->service->register($fakeRequest([
        'user' => [
            'name' => 'Verified User',
            'phone' => '+966509999003',
            'code' => 'x',
        ],
    ]));

    expect($result['user']->hasVerifiedPhone())->toBeTrue();
});

test('register sets center status to invisible by default', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $result = $this->service->register($fakeRequest([
        'name' => 'Default Status Center',
        'contact_phone' => '+966110000004',
        'user' => [
            'name' => 'U4',
            'phone' => '+966509999004',
            'code' => 'c4',
        ],
    ]));

    expect($result['center']->status)->toBe(CenterStatus::INVISIBLE);

    $this->assertDatabaseHas('centers', [
        'id' => $result['center']->id,
        'status' => CenterStatus::INVISIBLE->value,
    ]);
});

test('register attaches user to center with correct pivot values', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $result = $this->service->register($fakeRequest([
        'name' => 'Pivot Test Center',
        'contact_phone' => '+966110000005',
        'user' => [
            'name' => 'Pivot User',
            'phone' => '+966509999005',
            'code' => 'c5',
        ],
    ]));

    $this->assertDatabaseHas('center_user_assignment', [
        'center_id' => $result['center']->id,
        'user_id' => $result['user']->id,
        'status' => ActivationStatus::ACTIVE->value,
        'is_primary' => true,
    ]);

    $pivot = \Illuminate\Support\Facades\DB::table('center_user_assignment')
        ->where('center_id', $result['center']->id)
        ->where('user_id', $result['user']->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->joined_at)->not->toBeNull();
    expect(str_starts_with((string) $pivot->joined_at, now()->toDateString()))->toBeTrue();

    $result['center']->load('users');
    expect($result['center']->users->firstWhere('id', $result['user']->id))->not->toBeNull();
});

test('register assigns owner role to primary user scoped by center team id', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $result = $this->service->register($fakeRequest([
        'name' => 'Role Assign Center',
        'contact_phone' => '+966110000055',
        'user' => [
            'name' => 'Role Owner',
            'phone' => '+966509999055',
            'code' => 'c55',
        ],
    ]));

    app(PermissionRegistrar::class)->setPermissionsTeamId($result['center']->id);

    $result['user']->unsetRelation('roles');
    expect($result['user']->hasRole(CenterUserRole::Owner->value))->toBeTrue();
});

test('register issues token only after transaction commits', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);

    $tokenIssuedBeforeUserExists = false;

    $this->auth
        ->shouldReceive('issueAppToken')
        ->once()
        ->withArgs(function ($user) use (&$tokenIssuedBeforeUserExists) {
            $tokenIssuedBeforeUserExists = ! \Modules\Centers\Models\User::query()
                ->where('id', $user->id)
                ->exists();

            return true;
        })
        ->andReturn('post-commit-token');

    $this->service->register($fakeRequest([
        'user' => [
            'name' => 'PostCommit',
            'phone' => '+966509999006',
            'code' => 'c6',
        ],
    ]));

    expect($tokenIssuedBeforeUserExists)->toBeFalse();
});

test('register description can be null', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $result = $this->service->register($fakeRequest([
        'description' => null,
        'user' => [
            'name' => 'NoDesc',
            'phone' => '+966509999007',
            'code' => 'c7',
        ],
    ]));

    expect($result['center']->description)->toBeNull();
    $this->assertDatabaseHas('centers', [
        'id' => $result['center']->id,
        'description' => null,
    ]);
});

test('register creates exactly one user, one center and one pivot record', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $beforeUsers = \Modules\Centers\Models\User::count();
    $beforeCenters = \Modules\Centers\Models\Center::count();
    $beforePivots = \Illuminate\Support\Facades\DB::table('center_user_assignment')->count();

    $this->service->register($fakeRequest([
        'user' => [
            'name' => 'Counter User',
            'phone' => '+966509999008',
            'code' => 'c8',
        ],
    ]));

    expect(\Modules\Centers\Models\User::count())->toBe($beforeUsers + 1);
    expect(\Modules\Centers\Models\Center::count())->toBe($beforeCenters + 1);
    expect(\Illuminate\Support\Facades\DB::table('center_user_assignment')->count())->toBe($beforePivots + 1);
});

test('register auto-generates slug derived from center name', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $result = $this->service->register($fakeRequest([
        'name' => 'My Awesome Center',
        'user' => [
            'name' => 'Slugger',
            'phone' => '+966509999009',
            'code' => 'c9',
        ],
    ]));

    expect($result['center']->slug)->toBe('my-awesome-center');
    $this->assertDatabaseHas('centers', [
        'id' => $result['center']->id,
        'slug' => 'my-awesome-center',
    ]);
});

test('register deduplicates slugs when multiple centers share the same name', function () use ($fakeRequest): void {
    $this->otp->shouldReceive('verify')->times(2)->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->times(2)->andReturn('t');

    $r1 = $this->service->register($fakeRequest([
        'name' => 'Dup Name Center',
        'contact_phone' => '+966110000071',
        'user' => [
            'name' => 'Dup1',
            'phone' => '+966509999071',
            'code' => 'c71',
        ],
    ]));

    $r2 = $this->service->register($fakeRequest([
        'name' => 'Dup Name Center',
        'contact_phone' => '+966110000072',
        'user' => [
            'name' => 'Dup2',
            'phone' => '+966509999072',
            'code' => 'c72',
        ],
    ]));

    expect($r1['center']->slug)->toBe('dup-name-center');
    expect($r2['center']->slug)->toBe('dup-name-center-1');
    expect($r1['center']->slug)->not->toBe($r2['center']->slug);
});

test('register uses provided city_id when passed', function () use ($fakeRequest): void {
    $city2 = \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'Second City', 'ar' => 'المدينة الثانية'],
    ]);

    $this->otp->shouldReceive('verify')->once()->andReturn(true);
    $this->auth->shouldReceive('issueAppToken')->once()->andReturn('t');

    $result = $this->service->register($fakeRequest([
        'city_id' => $city2->id,
        'user' => [
            'name' => 'City Scoped',
            'phone' => '+966509999088',
            'code' => 'c88',
        ],
    ]));

    expect($result['center']->city_id)->toBe($city2->id);
    $this->assertDatabaseHas('centers', [
        'id' => $result['center']->id,
        'city_id' => $city2->id,
    ]);
});
