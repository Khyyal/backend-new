<?php

namespace Modules\Centers\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Mail\CenterEmailVerificationMail;
use Modules\Centers\Models\Center;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Verifies a center's contact email with a 6-digit code mailed to it. The code
 * is stored hashed, expires after `TTL_MINUTES` and is cleared once used.
 */
class CenterEmailVerification
{
    public const TTL_MINUTES = 10;

    private const SEND_MAX_ATTEMPTS = 5;

    private const SEND_DECAY_SECONDS = 900;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const VERIFY_MAX_ATTEMPTS = 5;

    private const VERIFY_DECAY_SECONDS = 300;

    /**
     * @throws ValidationException when there is no email or it is already verified
     * @throws TooManyRequestsHttpException
     */
    public function send(Center $center, string $locale): void
    {
        $this->ensureCanVerify($center);

        $key = "center-email-send:{$center->getKey()}";

        if ($center->email_verification_sent_at !== null
            && $center->email_verification_sent_at->diffInSeconds(now(), true) < self::RESEND_COOLDOWN_SECONDS) {
            throw new TooManyRequestsHttpException(self::RESEND_COOLDOWN_SECONDS, __('centers::email_verification.cooldown'));
        }

        if (RateLimiter::tooManyAttempts($key, self::SEND_MAX_ATTEMPTS)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key), __('centers::email_verification.cooldown'));
        }

        RateLimiter::hit($key, self::SEND_DECAY_SECONDS);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $center->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'email_verification_sent_at' => now(),
        ])->save();

        Mail::to($center->email)->send(new CenterEmailVerificationMail($center->name, $code, self::TTL_MINUTES, $locale));
    }

    /**
     * @throws ValidationException when the code is wrong or expired
     * @throws TooManyRequestsHttpException
     */
    public function verify(Center $center, string $code): Center
    {
        $this->ensureCanVerify($center);

        $key = "center-email-verify:{$center->getKey()}";

        if (RateLimiter::tooManyAttempts($key, self::VERIFY_MAX_ATTEMPTS)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key), __('centers::email_verification.invalid'));
        }

        $valid = $center->email_verification_code !== null
            && $center->email_verification_expires_at?->isFuture()
            && Hash::check($code, $center->email_verification_code);

        if (! $valid) {
            RateLimiter::hit($key, self::VERIFY_DECAY_SECONDS);

            throw ValidationException::withMessages(['code' => [__('centers::email_verification.invalid')]]);
        }

        RateLimiter::clear($key);

        $center->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_sent_at' => null,
        ])->save();

        return $center;
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanVerify(Center $center): void
    {
        if ($center->email === null) {
            throw ValidationException::withMessages(['email' => [__('centers::email_verification.no_email')]]);
        }

        if ($center->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => [__('centers::email_verification.already_verified')]]);
        }
    }
}
