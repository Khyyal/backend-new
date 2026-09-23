<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Modules\Support\Models\Device;

class DeviceService
{
    /**
     * Register or update a device.
     */
    public function register(
        Model $deviceable,
        string $deviceIdentifier,
        string $fcmToken,
    ): Device {
        return $deviceable->devices()->updateOrCreate(
            [
                'device_identifier' => $deviceIdentifier,
            ],
            [
                'fcm_token' => $fcmToken,
            ],
        );
    }

    public function registerOrUpdateDevice(
        Model $deviceable,
        string $deviceIdentifier,
        ?string $fcmToken = null,
        ?string $platform = null,
        ?string $locale = null,
    ): Device {
        $payload = [
            'last_seen_at' => now(),
        ];

        if ($deviceable !== null) {
            $payload['deviceable_id'] = $deviceable->id;
            $payload['deviceable_type'] = get_class($deviceable);
        }

        if ($fcmToken !== null) {
            $payload['fcm_token'] = $fcmToken;
        }

        if ($platform !== null) {
            $payload['platform'] = $platform;
        }

        if ($locale !== null) {
            $payload['locale'] = $locale;
        }

        $attempts = 0;

        do {
            try {
                return Device::query()->updateOrCreate(
                    ['device_identifier' => $deviceIdentifier],
                    $payload
                );
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 2) {
                    throw $e;
                }
                usleep(10_000);
            }
        } while (true);
    }



    /**
     * Unregister a device.
     */
    public function unregister(
        Model $deviceable,
        string $deviceIdentifier,
    ): bool {
        return $deviceable->devices()
                ->where('device_identifier', $deviceIdentifier)
                ->delete() > 0;
    }

    /**
     * Check whether a device is registered.
     */
    public function exists(
        Model $deviceable,
        string $deviceIdentifier,
    ): bool {
        return $deviceable->devices()
            ->where('device_identifier', $deviceIdentifier)
            ->exists();
    }

    /**
     * Update a device FCM token.
     */
    public function updateToken(
        Model $deviceable,
        string $deviceIdentifier,
        string $fcmToken,
    ): bool {
        return $deviceable->devices()
                ->where('device_identifier', $deviceIdentifier)
                ->update([
                    'fcm_token' => $fcmToken,
                ]) > 0;
    }

    /**
     * Get all devices registered for a model.
     */
    public function getDevices(Model $deviceable): Collection
    {
        return $deviceable->devices()
            ->latest()
            ->get();
    }

    /**
     * Get all FCM tokens.
     */
    public function getTokens(Model $deviceable): Collection
    {
        return $deviceable->devices()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->pluck('fcm_token');
    }

    /**
     * Find a device by identifier.
     */
    public function find(
        Model $deviceable,
        string $deviceIdentifier,
    ): ?Device {
        return $deviceable->devices()
            ->where('device_identifier', $deviceIdentifier)
            ->first();
    }

    public function extractDeviceIdentifier(Request $request): ?string
    {
        $value = $request->header('X-Device-Identifier') ?? $request->input('device_identifier');

        if ($value === null) {
            return null;
        }

        $string = is_string($value) ? $value : (string) $value;

        return $string === '' ? null : $string;
    }
}
