<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Support\Models\Device;

trait HasDevices
{
    /**
     * Get all devices belonging to this model.
     */
    public function devices(): MorphMany
    {
        return $this->morphMany(Device::class, 'deviceable');
    }

    /**
     * Register a device for this model.
     */
    public function registerDevice(
        string $deviceIdentifier,
        string $fcmToken,
    ): Device {
        return $this->devices()->updateOrCreate(
            [
                'device_identifier' => $deviceIdentifier,
            ],
            [
                'fcm_token' => $fcmToken,
            ]
        );
    }

    /**
     * Remove a device by its identifier.
     */
    public function unregisterDevice(string $deviceIdentifier): bool
    {
        return $this->devices()
                ->where('device_identifier', $deviceIdentifier)
                ->delete() > 0;
    }

    /**
     * Check whether the model has a specific device.
     */
    public function hasDevice(string $deviceIdentifier): bool
    {
        return $this->devices()
            ->where('device_identifier', $deviceIdentifier)
            ->exists();
    }

    /**
     * Update a device's FCM token.
     */
    public function updateDeviceToken(
        string $deviceIdentifier,
        string $fcmToken,
    ): int {
        return $this->devices()
            ->where('device_identifier', $deviceIdentifier)
            ->update([
                'fcm_token' => $fcmToken,
            ]);
    }

    /**
     * Get all FCM tokens registered for this model.
     */
    public function deviceTokens()
    {
        return $this->devices()
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token');
    }
}
