<?php

namespace Modules\Support\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Http\Requests\LaunchDeviceRequest;
use Modules\Support\Services\DeviceService;

#[Group('Admin / Devices', 'Push notification devices (browsers) of the authenticated system user.')]
class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
    ) {}

    #[Endpoint('Register a device', 'Registers or refreshes the current system user\'s device with its FCM token, platform and locale. Idempotent; the admin panel calls it on every load and whenever the FCM token changes. Limited to 30 requests per minute.')]
    #[ApiResponse(['device' => ['device_identifier' => 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6', 'platform' => 'web', 'locale' => 'ar', 'linked' => true]], 200, 'Device registered or refreshed.')]
    #[ResponseField('device.device_identifier', 'string', 'The device ID.', example: 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6')]
    #[ResponseField('device.platform', 'string', '`web`, `ios` or `android`.', example: 'web')]
    #[ResponseField('device.locale', 'string', 'Push locale.', nullable: true, example: 'ar')]
    #[ResponseField('device.linked', 'boolean', 'Always `true`: the device belongs to the system user.', example: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The device identifier field is required.', 'errors' => ['device_identifier' => ['The device identifier field is required.']]], 422, 'Validation failed.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function store(LaunchDeviceRequest $request): JsonResponse
    {
        /** @var array{device_identifier: string, platform: string, fcm_token?: ?string, locale?: ?string} $validated */
        $validated = $request->validated();

        $device = $this->deviceService->launch(
            deviceIdentifier: $validated['device_identifier'],
            platform: DevicePlatform::from($validated['platform']),
            fcmToken: $validated['fcm_token'] ?? null,
            locale: $validated['locale'] ?? null,
            owner: $request->user(),
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

    #[Endpoint('Remove a device', 'Stops push notifications to that device for the current system user. Removing an unknown device is a no-op.')]
    #[UrlParam('deviceIdentifier', 'string', 'The device ID sent on registration.', example: 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6')]
    #[ApiResponse(status: 204, description: 'Removed. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function destroy(Request $request, string $deviceIdentifier): Response
    {
        $this->deviceService->unregister($request->user(), $deviceIdentifier);

        return response()->noContent();
    }
}
