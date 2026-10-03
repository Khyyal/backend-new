<?php

namespace Modules\Purchase\Contracts;

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
     *
     * @return array{payment_order_id: string, payment_status: \Modules\Purchase\Enums\PaymentStatus}|null
     */
    public function handle(WebhookEvent $event): ?array;
}
