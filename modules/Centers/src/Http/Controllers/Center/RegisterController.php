<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Centers\Http\Requests\Center\RegisterCenterFormRequest;
use Modules\Centers\Http\Requests\Center\SendRegisterOtpRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Http\Resources\Center\UserResource;
use Modules\Centers\Services\CenterRegisterService;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Services\DeviceService;
use Modules\Support\Services\OtpVerificationService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Register', description: 'Center registration')]
class RegisterController extends Controller
{
    /**
     * Request a registration OTP for a new center user.
     *
     * Sends a one-time password (OTP) by SMS to the provided phone number. The
     * phone must not already belong to a registered center user. The returned
     * code is then submitted as `user.code` to `POST /centers/register`.
     *
     * **Request body:**
     * - `phone` — required; string, max 32 characters, not yet registered.
     *
     * @param  SendRegisterOtpRequest  $request  Validated via {@see SendRegisterOtpRequest}.
     * @return JsonResponse 200 with `message` and `expires_in_seconds`.
     */
    #[Response(
        status: 200,
        description: 'OTP generated and queued for delivery. Response includes a localized `message` and the TTL in `expires_in_seconds`.'
    )]
    #[Response(
        status: 422,
        description: 'Validation failure on `phone` (missing, too long, or already registered — error keyed under `phone`).'
    )]
    #[Response(
        status: 429,
        description: 'Too many OTP requests for this phone or this IP. Retry after the period returned in the `Retry-After` header.'
    )]
    public function sendCode(
        SendRegisterOtpRequest $request,
        OtpVerificationService $verification,
    ): JsonResponse {
        /** @var array{phone: string} $validated */
        $validated = $request->validated();

        $verification->send($validated['phone']);

        return response()->json([
            'message' => __('centers::messages.otp_sent'),
            'expires_in_seconds' => OtpVerificationService::DEFAULT_TTL_SECONDS,
        ]);
    }

    #[Response(
        status: 201,
        description: 'Registration succeeded. Returns the created `user`, the new `center` record, and a `token` Bearer token for immediate authentication.'
    )]
    #[Response(
        status: 422,
        description: 'Validation failure on any request field, or the OTP `user.code` is invalid/expired (error keyed under `user.code`).'
    )]
    #[Response(
        status: 429,
        description: 'Too many registration attempts. Retry after the period returned in the `Retry-After` header (10 req/min per IP).'
    )]
    public function store(
        RegisterCenterFormRequest $request,
        CenterRegisterService $service,
        DeviceService $deviceService,
    ): JsonResponse {
        $result = $service->register($request);

        $deviceId = $deviceService->extractDeviceIdentifier($request);
        if ($deviceId !== null) {
            $deviceService->attachTo(
                $result['user'],
                $deviceId,
                DevicePlatform::tryFrom((string) $request->validated('platform')),
            );
        }

        return response()->json([
            'user' => new UserResource($result['user']),
            'center' => new CenterResource($result['center']),
            'token' => $result['token'],
        ], ResponseAlias::HTTP_CREATED);
    }
}
