<?php

namespace Modules\Clients\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Http\Requests\LaunchDeviceRequest;
use Modules\Support\Services\DeviceService;

#[Group('Client / Device', 'Device registration for push notifications.')]
class LaunchController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Register the device on app launch', 'Call on every app start. Registers or refreshes the device with its FCM token, platform and locale. Without a token the device is stored unlinked and linked on `POST /api/v1/clients/auth/verify` when the same `device_identifier` is sent; with a client bearer token it is linked directly. Limited to 30 requests per minute.')]
    #[ApiResponse(['device' => ['device_identifier' => 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6', 'platform' => 'android', 'locale' => 'ar', 'linked' => true]], 200, 'Device registered or refreshed.')]
    #[ResponseField('device.device_identifier', 'string', 'The device ID.', example: 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6')]
    #[ResponseField('device.platform', 'string', '`ios` or `android`.', example: 'android')]
    #[ResponseField('device.locale', 'string', 'Push locale.', nullable: true, example: 'ar')]
    #[ResponseField('device.linked', 'boolean', 'Whether the device is linked to a client.', example: true)]
    #[ApiResponse(['message' => 'The device identifier field is required.', 'errors' => ['device_identifier' => ['The device identifier field is required.']]], 422, 'Validation failed.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function __invoke(LaunchDeviceRequest $request): JsonResponse
    {
        /** @var array{device_identifier: string, platform: string, fcm_token?: ?string, locale?: ?string} $validated */
        $validated = $request->validated();

        $device = $this->deviceService->launch(
            deviceIdentifier: $validated['device_identifier'],
            platform: DevicePlatform::from($validated['platform']),
            fcmToken: $validated['fcm_token'] ?? null,
            locale: $validated['locale'] ?? null,
            owner: $request->user('client'),
        );

        return response()->json([
            'device' => [
                'device_identifier' => $device->device_identifier,
                'platform' => $device->platform,
                'locale' => $device->locale,
                'linked' => $device->deviceable_id !== null,
            ],
        ]);
    }
}
