<?php

namespace Modules\Support\Support;

final class PhoneNumber
{
    /**
     * Normalize to international digits (e.g. `+966 50 123 4567`, `0501234567` → `966501234567`).
     * Returns null when the number cannot be a valid international number.
     */
    public static function normalize(?string $phone, string $defaultCountryCode = '966'): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = $defaultCountryCode.substr($digits, 1);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = $defaultCountryCode.$digits;
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? $digits : null;
    }
}
