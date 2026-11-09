<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Centers\Http\Requests\Center\SendLoginOtpRequest;
use Modules\Centers\Http\Requests\Center\VerifyLoginOtpRequest;
use Modules\Centers\Http\Resources\Center\UserResource;
use Modules\Centers\Services\CenterAuthService;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Services\DeviceService;
use Modules\Support\Services\OtpVerificationService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Center / Auth', 'Center user login by phone and OTP. Registration has its own endpoints.')]
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpVerificationService $verification,
        private readonly CenterAuthService $authService,
        private readonly DeviceService $deviceService,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Request a login code', 'Sends a one-time code by SMS to a registered center user. Then call `POST /api/v1/centers/auth/phone/verify`.')]
    #[ApiResponse(['message' => 'Verification code sent.', 'expires_in_seconds' => 300], 200, 'Code sent.')]
    #[ResponseField('message', 'string', 'Localized confirmation.', example: 'Verification code sent.')]
    #[ResponseField('expires_in_seconds', 'integer', 'Code lifetime in seconds.', example: 300)]
    #[ApiResponse(['message' => 'The selected phone is invalid.', 'errors' => ['phone' => ['The selected phone is invalid.']]], 422, 'Missing phone, or no center user has it.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many codes for this phone (5 per 15 minutes) or this IP (10 per minute); retry after `Retry-After`.')]
    public function login(SendLoginOtpRequest $request): JsonResponse
    {
        /** @var array{phone: string} $validated */
        $validated = $request->validated();
        $phone = $validated['phone'];

        $this->verification->send($phone);

        return response()->json([
            'message' => __('centers::messages.otp_sent'),
            'expires_in_seconds' => OtpVerificationService::DEFAULT_TTL_SECONDS,
        ]);
    }

    /**
     * @throws ValidationException When the OTP code is incorrect or expired.
     */
    #[Unauthenticated]
    #[Endpoint('Verify the login code', 'Returns an account-level token: list your centers with `GET /api/v1/centers`, then call `POST /api/v1/centers/{center}/access` for a center-scoped token. Marks the phone verified and links the launched device when `device_identifier` is sent.')]
    #[ApiResponse(['user' => ['id' => 15, 'name' => 'Khalid Al-Harbi', 'phone' => '+966500000002'], 'token' => '31|Q2VudGVyVXNlclRva2Vu', 'token_type' => 'Bearer'], 200, 'Logged in.')]
    #[ResponseField('user', 'object', 'The center user (`UserResource`): id, name, phone.')]
    #[ResponseField('token', 'string', 'Sanctum token for the account-level center routes.', example: '31|Q2VudGVyVXNlclRva2Vu')]
    #[ResponseField('token_type', 'string', 'Always `Bearer`.', example: 'Bearer')]
    #[ApiResponse(['message' => 'Invalid or expired verification code.', 'errors' => ['code' => ['Invalid or expired verification code.']]], 422, 'Validation failed, or the code is wrong or expired.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many wrong codes for this phone (5 per 5 minutes); retry after `Retry-After`.')]
    public function verify(VerifyLoginOtpRequest $request): JsonResponse
    {
        /** @var array{phone: string, code: string} $validated */
        $validated = $request->validated();
        $phone = $validated['phone'];
        $code = $validated['code'];

        if (! $this->verification->verify($phone, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('centers::otp.invalid')],
            ]);
        }

        $user = $this->authService->findByPhone($phone);
        if ($user === null) {
            throw ValidationException::withMessages([
                'phone' => [__('centers::auth.phone_not_registered_please_register')],
            ]);
        }

        $this->authService->markPhoneVerifiedIfNeeded($user);

        $deviceId = $this->deviceService->extractDeviceIdentifier($request);
        if ($deviceId !== null) {
            $this->deviceService->attachTo(
                $user,
                $deviceId,
                DevicePlatform::tryFrom((string) $request->validated('platform')),
            );
        }

        $token = $this->authService->issueAppToken($user, $request);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], ResponseAlias::HTTP_OK);
    }
}
