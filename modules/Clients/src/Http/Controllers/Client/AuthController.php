<?php

namespace Modules\Clients\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Requests\Client\LoginOtpRequest;
use Modules\Clients\Http\Requests\Client\VerifyOtpRequest;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Services\ClientAuthService;
use Modules\Support\Services\DeviceService;
use Modules\Support\Services\OtpVerificationService;


#[Group(name: 'Client / Auth', description: 'Client authentication via phone number + OTP.')]
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpVerificationService $verification,
        private readonly ClientAuthService $authService,
        private readonly DeviceService $deviceService,
    ) {}


    /**
     * Request a login OTP.
     *
     * Sends a 6-digit one-time password (OTP) to the provided phone number via
     * SMS. The OTP expires after `expires_in_seconds` (default 5 minutes) and
     * must be presented to the `/verify` endpoint to complete authentication.
     *
     * Rate-limited to 5 send attempts per phone number every 15 minutes.
     *
     * **Request body:**
     * - `phone_number` — required, international format (e.g. `+966501234567`),
     *   6–20 digits with optional leading `+`.
     *
     * @param LoginOtpRequest $request Validated via {@see LoginOtpRequest}.
     * @return JsonResponse 200 with `message` and `expires_in_seconds`.
     */
    #[Response(
        status: 200,
        description: 'OTP generated and queued for delivery. Response includes a `message` and the TTL in `expires_in_seconds`.'
    )]
    #[Response(
        status: 422,
        description: 'Validation failure on `phone_number` (missing, invalid format, or too long).'
    )]
    #[Response(
        status: 429,
        description: 'Too many OTP requests for this phone number. Retry after the period returned in the `Retry-After` header.'
    )]
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
     * Verify OTP and issue a Bearer token.
     *
     * Validates the 6-digit OTP sent to the phone number. When the OTP is
     * correct, a client record is looked up (or created on the fly for new
     * phone numbers), a device record is registered/updated when a
     * `X-Device-Identifier` header or `device_identifier` field is provided,
     * and a Sanctum Bearer token is minted for subsequent requests.
     *
     * **Request body:**
     * - `phone_number` — required; same number used in the `/login` call.
     * - `code`           — required; 6-digit OTP.
     * - `device_identifier` — optional; unique device ID for push-token
     *   registration. May also be passed via the `X-Device-Identifier` header.
     *
     * **Response body:**
     * - `token`              — Sanctum plain-text Bearer token.
     * - `token_type`         — Always `"Bearer"`.
     * - `needs_onboarding`   — `true` when `first_name` / `last_name` are not
     *   yet set on the client profile.
     * - `client`             — Client record as a `ClientResource`.
     *
     * @param VerifyOtpRequest $request Validated via {@see VerifyOtpRequest}.
     * @return JsonResponse 200 on success with token + client; 422 on bad input
     *                      or invalid code.
     *
     * @throws ValidationException When the OTP code is incorrect.
     */
    #[Response(
        status: 200,
        description: 'OTP verified. Returns a Bearer `token`, `token_type`, the `needs_onboarding` flag and the `client` resource (with `city` loaded if present).'
    )]
    #[Response(
        status: 422,
        description: 'Validation error on request fields, or the OTP `code` is invalid/expired (error keyed under `code`).'
    )]
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
            $this->deviceService->registerOrUpdateDevice($client, $deviceId);
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
