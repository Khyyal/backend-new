<?php

namespace Modules\Homepage\Support;

/**
 * Great-circle distance in kilometres between a row's lat/lng columns and a point.
 * The SQL has three placeholders: latitude, latitude, longitude, in that order.
 */
final class Haversine
{
    public static function sql(string $latitudeColumn = 'lat', string $longitudeColumn = 'lng'): string
    {
        return "6371 * ACOS(MIN(1, MAX(-1,
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
