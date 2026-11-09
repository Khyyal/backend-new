<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Centers\Http\Requests\Center\RegisterCenterFormRequest;
use Modules\Centers\Http\Requests\Center\SendRegisterOtpRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Http\Resources\Center\UserResource;
use Modules\Centers\Services\CenterRegisterService;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Services\DeviceService;
use Modules\Support\Services\OtpVerificationService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Center / Register', 'Self-service center registration. New centers start `invisible` and `pending` until approved.')]
class RegisterController extends Controller
{
    #[Unauthenticated]
    #[Endpoint('Request a registration code', 'Sends a one-time code by SMS to a phone that is not yet a center user. Submit it as `user.code` on `POST /api/v1/centers/register`.')]
    #[ApiResponse(['message' => 'Verification code sent.', 'expires_in_seconds' => 300], 200, 'Code sent.')]
    #[ResponseField('message', 'string', 'Localized confirmation.', example: 'Verification code sent.')]
    #[ResponseField('expires_in_seconds', 'integer', 'Code lifetime in seconds.', example: 300)]
    #[ApiResponse(['message' => 'The phone has already been taken.', 'errors' => ['phone' => ['The phone has already been taken.']]], 422, 'Missing phone, or it is already registered.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many codes for this phone (5 per 15 minutes) or this IP (10 per minute); retry after `Retry-After`.')]
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

    #[Unauthenticated]
    #[Endpoint('Register a center', 'Creates the center and its owner (primary user) after checking the OTP, and returns an account-level token. Links the launched device when `device_identifier` is sent. Limited to 10 requests per minute.')]
    #[ApiResponse(['user' => ['id' => 15, 'name' => 'Khalid Al-Harbi', 'phone' => '+966500000002'], 'center' => ['id' => 8, 'name' => 'Al Fursan Stables', 'slug' => 'al-fursan-stables', 'contact_phone' => '+966500000001', 'email' => 'info@alfursan.sa', 'social_links' => null, 'site_theme' => null, 'custom_site_enabled' => false, 'domain' => null, 'status' => 'invisible', 'state' => 'pending', 'points' => 0, 'address' => null, 'lat' => null, 'lng' => null, 'description' => null], 'token' => '31|Q2VudGVyVXNlclRva2Vu'], 201, 'Registered.')]
    #[ResponseField('user', 'object', 'The owner (`UserResource`): id, name, phone.')]
    #[ResponseField('center', 'object', 'The new center (`CenterResource` without relations).')]
    #[ResponseField('token', 'string', 'Sanctum token for the account-level center routes.', example: '31|Q2VudGVyVXNlclRva2Vu')]
    #[ApiResponse(['message' => 'Invalid or expired verification code.', 'errors' => ['user.code' => ['Invalid or expired verification code.']]], 422, 'Validation failed, or `user.code` is wrong or expired.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
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
