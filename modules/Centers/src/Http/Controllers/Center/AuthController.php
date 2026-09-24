<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Http\Requests\Center\SendLoginOtpRequest;
use Modules\Centers\Http\Requests\Center\VerifyLoginOtpRequest;
use Modules\Centers\Http\Resources\Center\UserResource;
use Modules\Centers\Services\CenterAuthService;
use Modules\Support\Services\OtpVerificationService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Auth', description: 'Center user authentication via phone number + OTP (login only, registration separate).')]
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpVerificationService $verification,
        private readonly CenterAuthService      $authService,
    ) {}

    /**
     * Request a login OTP for an existing center user.
     *
     * Sends a 4-digit one-time password (OTP) to the provided phone number via
     * SMS. The phone number must already belong to a registered center user;
     * otherwise a validation error instructing the caller to register is
     * returned. The OTP expires after `expires_in_seconds` (default 5 minutes)
     * and must be presented to the `/phone/verify` endpoint to complete
     * authentication.
     *
     * Rate-limited twice: once at the route level (IP-based throttle), and
     * again internally by OtpVerificationService (5 sends per phone per 15
     * minutes). Either limiter triggers a 429 with `Retry-After`.
     *
     * **Request body:**
     * - `phone` — required; string, max 32 characters. Must match a phone
     *   already registered in `center_users.phone`.
     *
     * @param SendLoginOtpRequest $request Validated via {@see SendLoginOtpRequest}.
     * @return JsonResponse 200 with `message` and `expires_in_seconds`.
     */
    #[Response(
        status: 200,
        description: 'OTP generated and queued for delivery. Response includes a localized `message` and the TTL in `expires_in_seconds`. No token or user data is returned at this stage.'
    )]
    #[Response(
        status: 422,
        description: 'Validation failure on `phone` (missing, too long, or there is no registered center user for this number — error body includes a "please register first" message keyed under `phone`).'
    )]
    #[Response(
        status: 429,
        description: 'Too many OTP requests for this phone or this IP. Retry after the period returned in the `Retry-After` header.'
    )]
    public function login(SendLoginOtpRequest $request): JsonResponse
    {
        /** @var array{phone: string} $validated */
        $validated = $request->validated();
        $phone = $validated['phone'];

        $this->verification->send($phone);

        return response()->json([
            'message' => __('messages.otp_sent', ['default' => 'Verification code sent.']),
            'expires_in_seconds' => OtpVerificationService::DEFAULT_TTL_SECONDS,
        ]);
    }

    /**
     * Verify OTP and issue a Bearer token for a center user.
     *
     * Validates the 4-digit OTP previously sent to the phone number. When the
     * OTP is correct, the center user record is loaded by phone,
     * `phone_verified_at` is timestamped if not already set, and a Sanctum
     * Bearer token is minted. The response shape matches
     * `RegisterController::store()` but omits the `center` key (use
     * `GET /centers` followed by `POST /centers/{center}/access` for
     * center-scoped credentials).
     *
     * Too many wrong verification attempts for the same phone number surface
     * as a 429 with `Retry-After` (distinct from a 422 wrong-code response).
     *
     * **Request body:**
     * - `phone` — required; same number used in the `/phone` call.
     * - `code`  — required; 4-digit OTP.
     *
     * **Response body:**
     * - `user`       — Authenticated `UserResource`.
     * - `token`      — Sanctum plain-text Bearer token.
     * - `token_type` — Always `"Bearer"`.
     *
     * @param VerifyLoginOtpRequest $request Validated via {@see VerifyLoginOtpRequest}.
     * @return JsonResponse 200 on success with token + user; 422 on bad input or
     *                      invalid code; 429 when the verify limiter is exhausted.
     *
     * @throws ValidationException When the OTP code is incorrect or expired.
     */
    #[Response(
        status: 200,
        description: 'OTP verified. Returns the authenticated `user` as a `UserResource`, a Bearer `token`, and `token_type`.'
    )]
    #[Response(
        status: 422,
        description: 'Validation error on request fields, or the OTP `code` is invalid/expired (error keyed under `code`).'
    )]
    #[Response(
        status: 429,
        description: 'Too many failed OTP verification attempts for this phone number. Retry after the period returned in the `Retry-After` header.'
    )]
    public function verify(VerifyLoginOtpRequest $request): JsonResponse
    {
        /** @var array{phone: string, code: string} $validated */
        $validated = $request->validated();
        $phone = $validated['phone'];
        $code = $validated['code'];

        if (! $this->verification->verify($phone, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('otp.invalid', ['default' => 'Invalid or expired verification code.'])],
            ]);
        }

        $user = $this->authService->findByPhone($phone);
        if ($user === null) {
            throw ValidationException::withMessages([
                'phone' => [__('auth.phone_not_registered_please_register', ['default' => 'This phone number is not registered. Please register first.'])],
            ]);
        }

        $this->authService->markPhoneVerifiedIfNeeded($user);

        $token = $this->authService->issueAppToken($user, $request);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], ResponseAlias::HTTP_OK);
    }
}
