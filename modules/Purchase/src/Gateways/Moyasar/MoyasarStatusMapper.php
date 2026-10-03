<?php

namespace Modules\Purchase\Gateways\Moyasar;

use Modules\Purchase\Enums\PaymentStatus;

/**
 * Maps Moyasar provider status strings to internal PaymentStatus enum.
 * Raw moyasar_status always preserved in provider_data.moyasar_status.
 *
 * Rules:
 *   initiated  → Processing
 *   paid       → Succeeded
 *   authorized → Processing (not auto-Succeeded; caller may define future policy)
 *   captured   → Succeeded
 *   failed     → Failed
 *   voided     → Cancelled
 *   refunded   → null (no auto state change; record in provider_data only)
 *   verified   → null (no auto state change by default)
 */
class MoyasarStatusMapper
{
    public const STATES_THAT_MAP = [
        'initiated' => PaymentStatus::Processing,
        'paid' => PaymentStatus::Succeeded,
        'authorized' => PaymentStatus::Processing,
        'captured' => PaymentStatus::Succeeded,
        'failed' => PaymentStatus::Failed,
        'voided' => PaymentStatus::Cancelled,
    ];

    public const NULL_STATES = [
        'refunded',
        'verified',
    ];

    public function map(string $rawStatus): ?PaymentStatus
    {
        $key = strtolower(trim($rawStatus));

        if (in_array($key, self::NULL_STATES, true)) {
            return null;
        }

        return self::STATES_THAT_MAP[$key] ?? null;
    }

    public function mapOrFallback(string $rawStatus, PaymentStatus $fallback = PaymentStatus::Pending): PaymentStatus
    {
        $mapped = $this->map($rawStatus);

        return $mapped ?? $fallback;
    }

    public function isRefunded(string $rawStatus): bool
    {
        return strtolower(trim($rawStatus)) === 'refunded';
    }

    public function isVerified(string $rawStatus): bool
    {
        return strtolower(trim($rawStatus)) === 'verified';
    }
}
