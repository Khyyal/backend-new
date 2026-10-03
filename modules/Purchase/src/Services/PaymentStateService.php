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
