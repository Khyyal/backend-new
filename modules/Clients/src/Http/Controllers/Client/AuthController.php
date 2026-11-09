<?php

namespace Modules\Clients\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Clients\Http\Requests\Client\LoginOtpRequest;
use Modules\Clients\Http\Requests\Client\VerifyOtpRequest;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Services\ClientAuthService;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Services\DeviceService;
use Modules\Support\Services\OtpVerificationService;

#[Group('Client / Auth', 'Client login by phone and OTP. New phones get an account on first verification.')]
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpVerificationService $verification,
        private readonly ClientAuthService $authService,
        private readonly DeviceService $deviceService,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Request a login code', 'Sends a 4-digit code by SMS. Limited to 5 codes per phone every 15 minutes. Then call `POST /api/v1/clients/auth/verify`.')]
    #[ApiResponse(['message' => 'messages.otp_sent', 'expires_in_seconds' => 300], 200, 'Code sent.')]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.otp_sent')]
    #[ResponseField('expires_in_seconds', 'integer', 'Code lifetime in seconds.', example: 300)]
    #[ApiResponse(['message' => 'The phone number field format is invalid.', 'errors' => ['phone_number' => ['The phone number field format is invalid.']]], 422, 'Invalid phone.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many codes for this phone; retry after `Retry-After`.')]
    public function login(LoginOtpRequest $request): JsonResponse
    {
        $phone = (string) $request->validated()['phone_number'];
        $this->verification->send($phone);

        return response()->json([
            'message' => __('messages.otp_sent'),
            'expires_in_seconds' => OtpVerificationService::DEFAULT_TTL_SECONDS,
        ]);
    }

    /**
     * @throws ValidationException When the OTP code is incorrect.
     */
    #[Unauthenticated]
    #[Endpoint('Verify the login code', 'Finds or creates the client by phone, links the launched device when `device_identifier` is sent, and issues a Sanctum token. When `needs_onboarding` is true, send the names to `PUT /api/v1/clients/profile`.')]
    #[ApiResponse(['token' => '45|Q2xpZW50VG9rZW4', 'token_type' => 'Bearer', 'needs_onboarding' => false, 'client' => ['id' => 77, 'first_name' => 'Noura', 'last_name' => 'Al-Qahtani', 'phone_number' => '+966500000003', 'city_id' => 1, 'city' => ['id' => 1, 'name' => 'Riyadh', 'radius' => 40], 'needs_onboarding' => false]], 200, 'Logged in.')]
    #[ResponseField('token', 'string', 'Sanctum token for the `clients/*` routes.', example: '45|Q2xpZW50VG9rZW4')]
    #[ResponseField('token_type', 'string', 'Always `Bearer`.', example: 'Bearer')]
    #[ResponseField('needs_onboarding', 'boolean', 'True while the first or last name is missing.', example: false)]
    #[ResponseField('client', 'object', 'The client (`ClientResource`, with city).')]
    #[ApiResponse(['message' => 'messages.invalid_otp', 'errors' => ['code' => ['messages.invalid_otp']]], 422, 'Validation failed, or the code is wrong or expired.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many wrong codes for this phone; retry after `Retry-After`.')]
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $phone = (string) $validated['phone_number'];
        $code = (string) $validated['code'];

        if (! $this->verification->verify($phone, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('messages.invalid_otp')],
            ]);
        }

        $client = $this->authService->findOrCreateByPhone($phone);
        $client = $client->fresh() ?? $client;

        $deviceId = $this->deviceService->extractDeviceIdentifier($request);
        if ($deviceId !== null) {
            $this->deviceService->attachTo(
                $client,
                $deviceId,
                DevicePlatform::tryFrom((string) $request->validated('platform')),
            );
        }
        $client->load('city');
        $token = $this->authService->issueAppToken($client, $request instanceof Request ? $request : null);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'needs_onboarding' => $client->needs_onboarding,
            'client' => ClientResource::make($client),
        ]);
    }
}
