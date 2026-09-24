<?php

namespace Modules\Support\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Support\Models\OTP;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class OtpVerificationService
{
    public const DEFAULT_TTL_SECONDS = 300;

    private const SEND_MAX_ATTEMPTS = 5;
    private const SEND_DECAY_SECONDS = 900; // 15 minutes

    private const VERIFY_MAX_ATTEMPTS = 5;
    private const VERIFY_DECAY_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly SMSService $smsService,
    ) {
    }

    /**
     * Generate and send an OTP.
     */
    public function send(
        string $phoneNumber,
        int $ttl = self::DEFAULT_TTL_SECONDS,
    ): void {
        $this->ensureCanSend($phoneNumber);

        RateLimiter::hit(
            $this->sendRateLimitKey($phoneNumber),
            self::SEND_DECAY_SECONDS
        );

        /*
         * Development OTP:
         *
         * We don't create an OTP record and we don't send an SMS.
         * The configured development code will be accepted by verify().
         */
        if ($this->isDevPhone($phoneNumber)) {
            return;
        }

        // Invalidate any previous unused OTPs.
        $this->invalidatePreviousOtps($phoneNumber);

        $code = $this->generateCode();

        OTP::create([
            'phone_number' => $phoneNumber,
            'code' => Hash::make($code),
            'expires_at' => now()->addSeconds($ttl),
        ]);

        $this->smsService->sendOTP(
            $phoneNumber,
            $code,
        );
    }

    /**
     * Verify an OTP.
     */
    public function verify(
        string $phoneNumber,
        string $code,
    ): bool {
        $this->ensureCanVerify($phoneNumber);

        /*
         * Development OTP.
         */
        if ($this->isDevOtp($phoneNumber, $code)) {
            RateLimiter::clear(
                $this->verifyRateLimitKey($phoneNumber)
            );

            return true;
        }

        $otp = OTP::query()
            ->where('phone_number', $phoneNumber)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || ! Hash::check($code, $otp->code)) {
            RateLimiter::hit(
                $this->verifyRateLimitKey($phoneNumber),
                self::VERIFY_DECAY_SECONDS
            );

            return false;
        }

        $otp->update([
            'verified_at' => now(),
        ]);

        RateLimiter::clear(
            $this->verifyRateLimitKey($phoneNumber)
        );

        return true;
    }

    /**
     * Generate a cryptographically secure 4-digit OTP.
     */
    private function generateCode(): string
    {
        return str_pad(
            (string) random_int(0, 999999),
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Invalidate previous unused OTPs.
     */
    private function invalidatePreviousOtps(string $phoneNumber): void
    {
        OTP::query()
            ->where('phone_number', $phoneNumber)
            ->whereNull('verified_at')
            ->update([
                'verified_at' => now(),
            ]);
    }

    /**
     * Check whether sending another OTP is allowed.
     */
    private function ensureCanSend(string $phoneNumber): void
    {
        $key = $this->sendRateLimitKey($phoneNumber);

        if (! RateLimiter::tooManyAttempts(
            $key,
            self::SEND_MAX_ATTEMPTS
        )) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw new TooManyRequestsHttpException(
            $seconds,
            __('otp.errors.tooManyRequests', ['seconds' => $seconds]),
        );
    }

    /**
     * Check whether another verification attempt is allowed.
     */
    private function ensureCanVerify(string $phoneNumber): void
    {
        $key = $this->verifyRateLimitKey($phoneNumber);

        if (! RateLimiter::tooManyAttempts(
            $key,
            self::VERIFY_MAX_ATTEMPTS
        )) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw new TooManyRequestsHttpException(
            $seconds,
            __('otp.errors.tooManyVerifyAttempts', ['seconds' => $seconds]),
        );
    }

    /**
     * Check if this is a configured development phone.
     */
    private function isDevPhone(string $phoneNumber): bool
    {
        if (! config('otp.dev.enabled')) {
            return false;
        }

        if (! app()->environment([
            'local',
            'testing',
            'staging',
        ])) {
            return false;
        }

        return in_array(
            $phoneNumber,
            config('otp.dev.phones', []),
            true
        );
    }

    /**
     * Check whether the development OTP is valid.
     */
    private function isDevOtp(
        string $phoneNumber,
        string $code,
    ): bool {
        if (! $this->isDevPhone($phoneNumber)) {
            return false;
        }

        return hash_equals(
            (string) config('otp.dev.code'),
            $code
        );
    }

    /**
     * Rate-limit key for sending OTPs.
     */
    private function sendRateLimitKey(string $phoneNumber): string
    {
        return 'otp:send:' . $phoneNumber;
    }

    /**
     * Rate-limit key for OTP verification.
     */
    private function verifyRateLimitKey(string $phoneNumber): string
    {
        return 'otp:verify:' . $phoneNumber;
    }
}
