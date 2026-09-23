<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Support\Models\OTP;
use Modules\Support\Services\OtpVerificationService;
use Modules\Support\Services\SMSService;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->smsService = Mockery::mock(SMSService::class);
    $this->service = new OtpVerificationService($this->smsService);
});

describe('send', function () {

    test('creates otp record and sends sms for normal phone', function () {
        $phone = '+966500000001';

        $this->smsService
            ->shouldReceive('sendOTP')
            ->once()
            ->with($phone, Mockery::type('string'));

        $this->service->send($phone);

        $this->assertDatabaseHas('otps', [
            'phone_number' => $phone,
            'verified_at' => null,
        ]);

        $otp = OTP::where('phone_number', $phone)->first();
        expect($otp->expires_at->timestamp)->toBeGreaterThanOrEqual(
            now()->addSeconds(OtpVerificationService::DEFAULT_TTL_SECONDS - 1)->timestamp
        );
    });

    test('uses custom ttl when provided', function () {
        $phone = '+966500000001';
        $customTtl = 60;

        $this->smsService->shouldReceive('sendOTP')->once();

        $this->service->send($phone, $customTtl);

        $otp = OTP::where('phone_number', $phone)->first();
        expect($otp->expires_at->timestamp)->toBeGreaterThanOrEqual(
            now()->addSeconds($customTtl - 1)->timestamp
        );
        expect($otp->expires_at->timestamp)->toBeLessThanOrEqual(
            now()->addSeconds($customTtl + 1)->timestamp
        );
    });

    test('invalidates previous unused otps when sending new one', function () {
        $phone = '+966500000001';

        $previous = OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make('1111'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        $this->smsService->shouldReceive('sendOTP')->once();

        $this->service->send($phone);

        $previous->refresh();
        expect($previous->verified_at)->not->toBeNull();

        $new = OTP::where('phone_number', $phone)->latest('id')->first();
        expect($new->id)->not->toBe($previous->id);
        expect($new->verified_at)->toBeNull();
    });

    test('hits send rate limiter', function () {
        $phone = '+966500000001';
        $key = 'otp:send:' . $phone;

        $this->smsService->shouldReceive('sendOTP')->times(3);

        expect(RateLimiter::attempts($key))->toBe(0);

        $this->service->send($phone);
        $this->service->send($phone);
        $this->service->send($phone);

        expect(RateLimiter::attempts($key))->toBe(3);
    });

    test('throws runtime exception after max send attempts', function () {
        $phone = '+966500000001';
        $key = 'otp:send:' . $phone;

        $this->smsService->shouldReceive('sendOTP')->times(5);

        for ($i = 0; $i < 5; $i++) {
            $this->service->send($phone);
        }

        $this->expectException(RuntimeException::class);
        $this->service->send($phone);
    });

    test('skips otp creation and sms for dev phone in testing env', function () {
        $devPhone = '+966500000999';

        config()->set('otp.dev.enabled', true);
        config()->set('otp.dev.phones', [$devPhone]);
        config()->set('otp.dev.code', '123456');

        $this->smsService->shouldNotReceive('sendOTP');

        $this->service->send($devPhone);

        $this->assertDatabaseMissing('otps', [
            'phone_number' => $devPhone,
        ]);
    });

    test('normal send flow when dev enabled but phone not in dev list', function () {
        $normalPhone = '+966500000001';
        $devPhone = '+966500000999';

        config()->set('otp.dev.enabled', true);
        config()->set('otp.dev.phones', [$devPhone]);

        $this->smsService->shouldReceive('sendOTP')->once()->with($normalPhone, Mockery::type('string'));

        $this->service->send($normalPhone);

        $this->assertDatabaseHas('otps', [
            'phone_number' => $normalPhone,
        ]);
    });

    test('dev phone is treated as normal when dev disabled', function () {
        $devPhone = '+966500000999';

        config()->set('otp.dev.enabled', false);
        config()->set('otp.dev.phones', [$devPhone]);

        $this->smsService->shouldReceive('sendOTP')->once();

        $this->service->send($devPhone);

        $this->assertDatabaseHas('otps', [
            'phone_number' => $devPhone,
        ]);
    });

    test('generated code is a 4-6 digit numeric string', function () {
        $phone = '+966500000001';
        $capturedCode = null;

        $this->smsService
            ->shouldReceive('sendOTP')
            ->once()
            ->with($phone, Mockery::on(function ($code) use (&$capturedCode) {
                $capturedCode = $code;
                return true;
            }));

        $this->service->send($phone);

        expect($capturedCode)->not->toBeNull();
        expect((string) $capturedCode)->toMatch('/^[0-9]{4,6}$/');
    });

    test('generated code matches stored hash', function () {
        $phone = '+966500000001';
        $capturedCode = null;

        $this->smsService
            ->shouldReceive('sendOTP')
            ->once()
            ->with($phone, Mockery::on(function ($code) use (&$capturedCode) {
                $capturedCode = $code;
                return true;
            }));

        $this->service->send($phone);

        $otp = OTP::where('phone_number', $phone)->first();
        expect(Hash::check($capturedCode, $otp->code))->toBeTrue();
    });

});

describe('verify', function () {

    test('returns true and marks verified for correct code', function () {
        $phone = '+966500000001';
        $code = '4321';

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        $result = $this->service->verify($phone, $code);

        expect($result)->toBeTrue();

        $otp = OTP::where('phone_number', $phone)->first();
        expect($otp->verified_at)->not->toBeNull();
    });

    test('clears verify rate limiter on successful verify', function () {
        $phone = '+966500000001';
        $code = '4321';
        $key = 'otp:verify:' . $phone;

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        RateLimiter::hit($key, 300);
        RateLimiter::hit($key, 300);
        expect(RateLimiter::attempts($key))->toBe(2);

        $this->service->verify($phone, $code);

        expect(RateLimiter::attempts($key))->toBe(0);
    });

    test('returns false for wrong code', function () {
        $phone = '+966500000001';

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make('4321'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        $result = $this->service->verify($phone, '0000');

        expect($result)->toBeFalse();

        $otp = OTP::where('phone_number', $phone)->first();
        expect($otp->verified_at)->toBeNull();
    });

    test('hits verify rate limiter on failed verification', function () {
        $phone = '+966500000001';
        $key = 'otp:verify:' . $phone;

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make('4321'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        expect(RateLimiter::attempts($key))->toBe(0);

        $this->service->verify($phone, '0000');
        $this->service->verify($phone, '1111');

        expect(RateLimiter::attempts($key))->toBe(2);
    });

    test('returns false when no otp exists for phone', function () {
        $result = $this->service->verify('+966500000999', '0000');

        expect($result)->toBeFalse();
    });

    test('returns false for expired otp', function () {
        $phone = '+966500000001';
        $code = '4321';

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make($code),
            'expires_at' => now()->subMinutes(1),
            'verified_at' => null,
        ]);

        $result = $this->service->verify($phone, $code);

        expect($result)->toBeFalse();

        $otp = OTP::where('phone_number', $phone)->first();
        expect($otp->verified_at)->toBeNull();
    });

    test('returns false for already verified otp', function () {
        $phone = '+966500000001';
        $code = '4321';

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now()->subMinute(),
        ]);

        $result = $this->service->verify($phone, $code);

        expect($result)->toBeFalse();
    });

    test('uses latest unverified otp when multiple exist', function () {
        $phone = '+966500000001';
        $oldCode = '1111';
        $newCode = '9999';

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make($oldCode),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'created_at' => now()->subMinutes(5),
        ]);

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make($newCode),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'created_at' => now(),
        ]);

        $result = $this->service->verify($phone, $newCode);
        expect($result)->toBeTrue();

        $latest = OTP::where('phone_number', $phone)->latest('id')->first();
        expect($latest->verified_at)->not->toBeNull();
    });

    test('returns true for dev phone with correct dev code in testing env', function () {
        $devPhone = '+966500000999';
        $devCode = '123456';

        config()->set('otp.dev.enabled', true);
        config()->set('otp.dev.phones', [$devPhone]);
        config()->set('otp.dev.code', $devCode);

        $result = $this->service->verify($devPhone, $devCode);

        expect($result)->toBeTrue();
    });

    test('clears verify rate limiter on successful dev otp verify', function () {
        $devPhone = '+966500000999';
        $devCode = '123456';
        $key = 'otp:verify:' . $devPhone;

        config()->set('otp.dev.enabled', true);
        config()->set('otp.dev.phones', [$devPhone]);
        config()->set('otp.dev.code', $devCode);

        RateLimiter::hit($key, 300);
        RateLimiter::hit($key, 300);

        $this->service->verify($devPhone, $devCode);

        expect(RateLimiter::attempts($key))->toBe(0);
    });

    test('returns false for dev phone with wrong dev code', function () {
        $devPhone = '+966500000999';

        config()->set('otp.dev.enabled', true);
        config()->set('otp.dev.phones', [$devPhone]);
        config()->set('otp.dev.code', '123456');

        $result = $this->service->verify($devPhone, '000000');

        expect($result)->toBeFalse();
    });

    test('dev otp does not bypass for non-dev phones', function () {
        $normalPhone = '+966500000001';

        config()->set('otp.dev.enabled', true);
        config()->set('otp.dev.phones', ['+966500000999']);
        config()->set('otp.dev.code', '123456');

        $result = $this->service->verify($normalPhone, '123456');

        expect($result)->toBeFalse();
    });

    test('dev otp requires correct code even when otp record exists', function () {
        $devPhone = '+966500000999';
        $devCode = '123456';

        config()->set('otp.dev.enabled', true);
        config()->set('otp.dev.phones', [$devPhone]);
        config()->set('otp.dev.code', $devCode);

        OTP::create([
            'phone_number' => $devPhone,
            'code' => Hash::make('random'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        $result = $this->service->verify($devPhone, $devCode);
        expect($result)->toBeTrue();
    });

    test('ensure can verify does not throw even after max attempts', function () {
        $phone = '+966500000001';
        $key = 'otp:verify:' . $phone;

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($key, 300);
        }

        OTP::create([
            'phone_number' => $phone,
            'code' => Hash::make('4321'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        $result = $this->service->verify($phone, '4321');
        expect($result)->toBeTrue();
    });

});
