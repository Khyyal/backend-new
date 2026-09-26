<?php

namespace Modules\Purchase\Gateways\Moyasar;

/**
 * Normalizes Moyasar's provider source.type and source.company into the internal
 * payment_type / provider_data fields.
 *
 * Raw source.type values are NEVER discarded; they are preserved in provider_data.
 *
 * Normalization table:
 *   creditcard  → card
 *   Apple Pay   → apple_pay
 *   Samsung Pay → samsung_pay
 *   STC Pay     → stc_pay
 *
 * source.company (e.g. "mada", "visa", "mastercard") is preserved in provider_data.source_company.
 */
class MoyasarSourceNormalizer
{
    public const MAP = [
        'creditcard' => 'card',
        'apple pay' => 'apple_pay',
        'samsung pay' => 'samsung_pay',
        'stc pay' => 'stc_pay',
        'card' => 'card',
        'apple_pay' => 'apple_pay',
        'samsung_pay' => 'samsung_pay',
        'stc_pay' => 'stc_pay',
    ];

    /**
     * Normalize a raw source.type from the provider into internal payment_type string.
     * Falls back to a sanitized lower-case version of the raw value if no match.
     */
    public function normalizePaymentType(string $rawSourceType): string
    {
        $key = strtolower(trim($rawSourceType));

        if (isset(self::MAP[$key])) {
            return self::MAP[$key];
        }

        // Sanitize fallback
        $sanitized = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? 'card';

        return trim($sanitized, '_') ?: 'card';
    }

    /**
     * Given a Moyasar source array {type, company, ...}, return the structured tuple:
     *   [
     *     payment_type: string (normalized for internal Payment.payment_type),
     *     raw_source_type: string (preserved for provider_data),
     *     source_company: string (mada/visa/... or ''),
     *   ]
     *
     * @param  array<string, mixed>  $source
     * @return array{payment_type: string, raw_source_type: string, source_company: string}
     */
    public function fromSource(array $source): array
    {
        $rawType = (string) ($source['type'] ?? '');
        $company = (string) ($source['company'] ?? ($source['scheme'] ?? ''));

        return [
            'payment_type' => $this->normalizePaymentType($rawType),
            'raw_source_type' => $rawType,
            'source_company' => $company,
        ];
    }
}
