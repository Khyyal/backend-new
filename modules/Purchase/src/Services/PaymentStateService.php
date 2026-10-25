<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Events\PaymentCancelled;
use Modules\Purchase\Events\PaymentExpired;
use Modules\Purchase\Events\PaymentFailed;
use Modules\Purchase\Events\PaymentProcessing;
use Modules\Purchase\Events\PaymentRefunded;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Models\Payment;

class PaymentStateService
{
    public function markProcessing(Payment $payment): Payment
    {
        return $this->transition(
            $payment,
            from: [PaymentStatus::Pending],
            to: PaymentStatus::Processing,
            eventClass: PaymentProcessing::class,
        );
    }

    public function markSucceeded(Payment $payment): Payment
    {
        return $this->transition(
            $payment,
            from: [PaymentStatus::Pending, PaymentStatus::Processing],
            to: PaymentStatus::Succeeded,
            eventClass: PaymentSucceeded::class,
            beforeSave: static function (Payment $p): void {
                if ($p->paid_at === null) {
                    $p->paid_at = Carbon::now();
                }
            }
        );
    }

    public function markFailed(Payment $payment): Payment
    {
        return $this->transition(
            $payment,
            from: [PaymentStatus::Pending, PaymentStatus::Processing],
            to: PaymentStatus::Failed,
            eventClass: PaymentFailed::class,
        );
    }

    public function cancel(Payment $payment): Payment
    {
        return $this->transition(
            $payment,
            from: [PaymentStatus::Pending, PaymentStatus::Processing],
            to: PaymentStatus::Cancelled,
            eventClass: PaymentCancelled::class,
        );
    }

    public function expire(Payment $payment): Payment
    {
        return $this->transition(
            $payment,
            from: [PaymentStatus::Pending, PaymentStatus::Processing],
            to: PaymentStatus::Expired,
            eventClass: PaymentExpired::class,
        );
    }

    /**
     * Records money given back on a succeeded payment. Refunds add up: the
     * payment stays `succeeded` while partially refunded and becomes
     * `refunded` once its whole amount has been returned. This is the only
     * way a succeeded payment changes.
     */
    public function recordRefund(Payment $payment, float $amount): Payment
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than 0.');
        }

        return DB::transaction(function () use ($payment, $amount): Payment {
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PaymentStatus::Succeeded) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot refund payment with status "%s". Only succeeded payments can be refunded.',
                    $locked->status->value
                ));
            }

            if ($amount > $locked->refundableAmount()) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot refund %.2f; only %.2f is left to refund.',
                    $amount,
                    $locked->refundableAmount()
                ));
            }

            $locked->refunded_amount = round((float) $locked->refunded_amount + $amount, 2);
            $locked->refunded_at = Carbon::now();

            if ($locked->refundableAmount() <= 0) {
                $locked->status = PaymentStatus::Refunded;
            }

            $locked->save();

            DB::afterCommit(static function () use ($locked, $amount): void {
                event(new PaymentRefunded($locked, $amount));
            });

            return $locked;
        });
    }

    /**
     * @param  array<int, PaymentStatus>  $from
     * @param  class-string  $eventClass
     * @param  (callable(Payment): void)|null  $beforeSave
     */
    private function transition(
        Payment $payment,
        array $from,
        PaymentStatus $to,
        string $eventClass,
        ?callable $beforeSave = null,
    ): Payment {
        return DB::transaction(function () use (
            $payment,
            $from,
            $to,
            $eventClass,
            $beforeSave,
        ): Payment {
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $to) {
                return $locked;
            }

            if ($locked->status === PaymentStatus::Succeeded) {
                throw new InvalidArgumentException(
                    'Cannot transition a succeeded payment. Succeeded payments are immutable.'
                );
            }

            if (! in_array($locked->status, $from, true)) {
                $fromValues = array_map(
                    static fn (PaymentStatus $s): string => $s->value,
                    $from
                );

                throw new InvalidArgumentException(
                    sprintf(
                        'Cannot transition payment from "%s" to "%s". Allowed from states: [%s].',
                        $locked->status->value,
                        $to->value,
                        implode(', ', $fromValues)
                    )
                );
            }

            $locked->status = $to;

            if ($beforeSave !== null) {
                $beforeSave($locked);
            }

            $locked->save();

            DB::afterCommit(static function () use ($locked, $eventClass): void {
                event(new $eventClass($locked));
            });

            return $locked;
        });
    }
}
