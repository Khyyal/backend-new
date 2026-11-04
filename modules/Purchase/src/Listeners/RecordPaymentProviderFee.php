<?php

namespace Modules\Purchase\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Purchase\Actions\ResolveProviderFee;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Models\Payment;

/**
 * Snapshots the provider fee once a payment succeeds. Refunds keep it: the
 * provider does not give its fee back.
 */
class RecordPaymentProviderFee implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private ResolveProviderFee $resolver) {}

    public function handle(PaymentSucceeded $event): void
    {
        $payment = Payment::query()->find($event->payment->getKey());

        if ($payment === null || $payment->provider_fee_amount !== null) {
            return;
        }

        $payment->update(['provider_fee_amount' => $this->resolver->execute($payment)]);
    }
}
