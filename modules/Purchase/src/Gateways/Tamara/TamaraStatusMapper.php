<?php

namespace Modules\Purchase\Gateways\Tamara;

use Modules\Purchase\Enums\PaymentStatus;

/**
 * Maps Tamara provider raw status strings to internal PaymentStatus enum.
 * Preserves raw status in provider_data; never discards it.
 * "refunded" returns null (no automatic payment state change; details in provider_data only).
 */
class TamaraStatusMapper
{
    public const MAP = [
        'new' => PaymentStatus::Processing,
        'approved' => PaymentStatus::Processing,
        'authorised' => PaymentStatus::Succeeded,
        'fully_captured' => PaymentStatus::Succeeded,
        'partially_captured' => PaymentStatus::Succeeded,
        'declined' => PaymentStatus::Failed,
        'canceled' => PaymentStatus::Cancelled,
        'cancelled' => PaymentStatus::Cancelled,
        'expired' => PaymentStatus::Expired,
    ];

    public const REFUNDED = 'refunded';

    public function map(string $rawStatus): ?PaymentStatus
    {
        $key = strtolower(trim($rawStatus));

        if ($key === self::REFUNDED) {
            return null;
        }

        return self::MAP[$key] ?? null;
    }

    public function mapOrFallback(string $rawStatus, PaymentStatus $fallback = PaymentStatus::Pending): PaymentStatus
    {
        $mapped = $this->map($rawStatus);

        return $mapped ?? $fallback;
    }

    public function isRefunded(string $rawStatus): bool
    {
        return strtolower(trim($rawStatus)) === self::REFUNDED;
    }
}
