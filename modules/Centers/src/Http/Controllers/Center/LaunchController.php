<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Http\Requests\LaunchDeviceRequest;
use Modules\Support\Services\DeviceService;

#[Group(name: 'Center / Device', description: 'Device registration for push notifications.')]
class LaunchController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
    ) {}

    /**
     * Register the device on app launch.
     *
     * Called every time the center app starts. Registers or refreshes the
     * device with its FCM token, platform and locale. Unauthenticated launches
     * store the device unlinked; it is linked to the center user on `/auth/phone/verify`
     * or `/register` when the same `device_identifier` is sent. When a Bearer
     * token is sent, the authenticated center user's device is updated directly.
     *
     * **Request body:**
     * - `device_identifier` — required; unique ID of the app install.
     * - `platform`          — required; `ios` or `android`.
     * - `fcm_token`         — optional; Firebase Cloud Messaging token.
     * - `locale`            — optional; device locale (e.g. `ar`, `en`).
     *
     * @param  LaunchDeviceRequest  $request  Validated via {@see LaunchDeviceRequest}.
     * @return JsonResponse 200 with the registered `device`.
     */
    #[Response(status: 200, description: 'Device registered or refreshed.')]
    #[Response(status: 422, description: 'Validation failure on `device_identifier`, `platform`, `fcm_token` or `locale`.')]
    public function __invoke(LaunchDeviceRequest $request): JsonResponse
    {
        /** @var array{device_identifier: string, platform: string, fcm_token?: ?string, locale?: ?string} $validated */
        $validated = $request->validated();

        $device = $this->deviceService->launch(
            deviceIdentifier: $validated['device_identifier'],
            platform: DevicePlatform::from($validated['platform']),
            fcmToken: $validated['fcm_token'] ?? null,
            locale: $validated['locale'] ?? null,
            owner: $request->user('center_user'),
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
