<?php

namespace Modules\Support\Support;

use Illuminate\Support\Facades\DB;

/**
 * Great-circle distance in kilometres between a row's lat/lng columns and a point.
 * The SQL has three placeholders: latitude, latitude, longitude, in that order.
 *
 * The clamp uses scalar MIN/MAX on SQLite and LEAST/GREATEST elsewhere (MySQL's
 * MIN/MAX are aggregates only).
 */
final class Haversine
{
    public static function sql(string $latitudeColumn = 'lat', string $longitudeColumn = 'lng', ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();
        [$least, $greatest] = $driver === 'sqlite' ? ['MIN', 'MAX'] : ['LEAST', 'GREATEST'];

        return "6371 * ACOS({$least}(1, {$greatest}(-1,
    SIN(RADIANS({$latitudeColumn})) * SIN(RADIANS(?))
    + COS(RADIANS({$latitudeColumn})) * COS(RADIANS(?))
      * COS(RADIANS(? - {$longitudeColumn}))
)))";
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    public static function bindings(float $lat, float $lng): array
    {
        return [$lat, $lat, $lng];
    }
}
