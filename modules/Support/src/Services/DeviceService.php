<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
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
}
