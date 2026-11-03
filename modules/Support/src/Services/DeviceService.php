<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Support\Enums\DevicePlatform;
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
     * Register or refresh a device when the app launches.
     *
     * Without an owner the device is stored unlinked until a user logs in on it;
     * with an owner, that owner's row for the device is updated instead.
     */
    public function launch(
        string $deviceIdentifier,
        DevicePlatform $platform,
        ?string $fcmToken = null,
        ?string $locale = null,
        ?Model $owner = null,
    ): Device {
        return $this->retryOnUniqueViolation(function () use ($deviceIdentifier, $platform, $fcmToken, $locale, $owner): Device {
            $device = $this->deviceQuery($deviceIdentifier, $platform, $owner)->first()
                ?? new Device([
                    'device_identifier' => $deviceIdentifier,
                    'platform' => $platform,
                    'deviceable_id' => $owner?->getKey(),
                    'deviceable_type' => $owner?->getMorphClass(),
                ]);

            $device->fill(array_filter([
                'fcm_token' => $fcmToken,
                'locale' => $locale,
            ], fn (?string $value): bool => $value !== null));

            $device->last_seen_at = now();
            $device->save();

            return $device;
        });
    }

    /**
     * Link the device the app launched with to the user that just logged in.
     *
     * Claims the unowned launch row when there is one, merging it into the owner's
     * existing row for the same device if needed, otherwise creates an owned row.
     */
    public function attachTo(
        Model $owner,
        string $deviceIdentifier,
        ?DevicePlatform $platform = null,
    ): Device {
        return $this->retryOnUniqueViolation(fn (): Device => DB::transaction(function () use ($owner, $deviceIdentifier, $platform): Device {
            $launched = Device::query()
                ->unowned()
                ->where('device_identifier', $deviceIdentifier)
                ->when($platform !== null, fn (Builder $query) => $query->where('platform', $platform))
                ->latest('last_seen_at')
                ->lockForUpdate()
                ->first();

            $platform ??= $launched?->platform;

            $owned = $this->deviceQuery($deviceIdentifier, $platform, $owner)->lockForUpdate()->first();

            if ($owned !== null && $launched !== null) {
                $owned->fill(array_filter([
                    'fcm_token' => $launched->fcm_token,
                    'locale' => $launched->locale,
                ], fn (?string $value): bool => $value !== null));
                $launched->delete();
            }

            $device = $owned ?? $launched ?? new Device([
                'device_identifier' => $deviceIdentifier,
                'platform' => $platform,
            ]);

            $device->deviceable()->associate($owner);
            $device->last_seen_at = now();
            $device->save();

            return $device;
        }));
    }

    /**
     * @return Builder<Device>
     */
    private function deviceQuery(string $deviceIdentifier, ?DevicePlatform $platform, ?Model $owner): Builder
    {
        return Device::query()
            ->where('device_identifier', $deviceIdentifier)
            ->when(
                $platform !== null,
                fn (Builder $query) => $query->where('platform', $platform),
                fn (Builder $query) => $query->whereNull('platform'),
            )
            ->when(
                $owner !== null,
                fn (Builder $query) => $query->whereMorphedTo('deviceable', $owner),
                fn (Builder $query) => $query->unowned(),
            );
    }

    /**
     * Retry once when a concurrent request inserted the same device first.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function retryOnUniqueViolation(callable $callback): mixed
    {
        $attempts = 0;

        do {
            try {
                return $callback();
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
