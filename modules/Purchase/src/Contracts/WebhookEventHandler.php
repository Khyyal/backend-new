<?php

namespace Modules\Purchase\Contracts;

use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\WebhookEvent;

interface WebhookEventHandler
{
    /**
     * Translate a provider webhook payload into the internal payment-action shape.
     *
     * Returns either:
     *  - null: No payment action required (informational / non-payment event).
     *  - array with keys:
     *      - payment_order_id: string (provider order reference stored on Payment.payment_order_id)
     *      - payment_status: \Modules\Purchase\Enums\PaymentStatus
     *      - payment_id: our Payment id when the provider echoes it back (optional fallback lookup)
     *      - authorize: true when the gateway must be asked to authorize the payment first (optional)
     *
     * @return array{payment_order_id: string, payment_status: PaymentStatus, payment_id?: string|null, authorize?: bool}|null
     */
    public function handle(WebhookEvent $event): ?array;
}
