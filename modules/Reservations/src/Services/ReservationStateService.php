<?php

namespace Modules\Reservations\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\PurchaseItem;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\CapacityLock;
use Modules\Reservations\Booking\ReservationRulesResolver;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ReservationCancelled;
use Modules\Reservations\Events\ReservationCompleted;
use Modules\Reservations\Events\ReservationConfirmed;
use Modules\Reservations\Events\ReservationMarkedNoShow;
use Modules\Reservations\Events\ReservationRescheduled;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Models\ReservationLine;
use Throwable;

/**
 * Moves a reservation through its lifecycle. Every transition locks the row,
 * checks the current status, saves, and fires its event after commit.
 */
class ReservationStateService
{
    public function __construct(
        private ReservationRulesResolver $rules,
        private CapacityLock $capacityLock,
        private PaymentGatewayManager $gateways,
        private PaymentStateService $paymentState,
    ) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function confirm(Reservation $reservation): Reservation
    {
        return $this->transition(
            $reservation,
            from: [ReservationStatus::Pending],
            to: ReservationStatus::Confirmed,
            action: 'confirmed',
            eventClass: ReservationConfirmed::class,
        );
    }

    /**
     * Gives a package credit back and refunds an online payment. A client must
     * cancel before the configured cutoff; the center and the system may not.
     * When the refund fails nothing is cancelled.
     *
     * @throws ReservationNotAllowedException
     */
    public function cancel(Reservation $reservation, ?Model $cancelledBy = null, ?string $reason = null): Reservation
    {
        return $this->transition(
            $reservation,
            from: [ReservationStatus::Pending, ReservationStatus::Confirmed],
            to: ReservationStatus::Cancelled,
            action: 'cancelled',
            eventClass: ReservationCancelled::class,
            beforeSave: function (Reservation $locked) use ($cancelledBy, $reason): void {
                if ($cancelledBy instanceof Client) {
                    $this->ensureBeforeClientCutoff($locked);
                }

                $this->restorePackageCredit($locked);

                $refund = $this->refund($locked);

                if ($refund !== null) {
                    $locked->metadata = [...($locked->metadata ?? []), 'refund' => $refund];
                }

                $locked->cancelled_at = now();
                $locked->cancelledBy()->associate($cancelledBy);
                $locked->cancellation_reason = $reason;
            },
        );
    }

    /**
     * @throws ReservationNotAllowedException
     */
    public function complete(Reservation $reservation): Reservation
    {
        return $this->transition(
            $reservation,
            from: [ReservationStatus::Confirmed],
            to: ReservationStatus::Completed,
            action: 'completed',
            eventClass: ReservationCompleted::class,
            beforeSave: fn (Reservation $locked) => $this->ensureStarted($locked),
        );
    }

    /**
     * @throws ReservationNotAllowedException
     */
    public function markNoShow(Reservation $reservation): Reservation
    {
        return $this->transition(
            $reservation,
            from: [ReservationStatus::Confirmed],
            to: ReservationStatus::NoShow,
            action: 'marked as no-show',
            eventClass: ReservationMarkedNoShow::class,
            beforeSave: fn (Reservation $locked) => $this->ensureStarted($locked),
        );
    }

    /**
     * Moves the reservation to another slot of the same service, re-checking
     * schedules, blocks and capacity. The price must not change: a move to a
     * differently priced slot is a cancel and a new booking.
     *
     * @throws ReservationNotAllowedException
     */
    public function reschedule(
        Reservation $reservation,
        CarbonImmutable $date,
        ?string $startTime = null,
        ?CarbonImmutable $endDate = null,
        ?int $trainerId = null,
    ): Reservation {
        return DB::transaction(function () use ($reservation, $date, $startTime, $endDate, $trainerId): Reservation {
            $locked = $this->lock($reservation);
            $this->ensureStatus($locked, [ReservationStatus::Pending, ReservationStatus::Confirmed], 'rescheduled');

            $trainerId ??= $locked->trainer_id;
            $this->capacityLock->acquire($locked->service, $trainerId);

            $quote = $this->rules->quote(new BookingRequest(
                service: $locked->service,
                client: $locked->client,
                date: $date,
                startTime: $startTime,
                endDate: $endDate,
                quantity: $locked->quantity,
                priceOptionId: $locked->price_option_id,
                trainerId: $trainerId,
                lines: $locked->lines->map(fn (ReservationLine $line): array => [
                    'price_option_id' => $line->price_option_id,
                    'quantity' => $line->quantity,
                ])->all(),
                clientPackage: $locked->clientPackage,
                ignoreReservationId: $locked->getKey(),
            ));

            if (round($quote->subtotal, 2) !== round((float) $locked->subtotal, 2)) {
                throw ReservationNotAllowedException::because('reschedule_price_changed');
            }

            $previous = [
                'date' => $locked->date->toDateString(),
                'end_date' => $locked->end_date?->toDateString(),
                'start_time' => $locked->start_time,
                'end_time' => $locked->end_time,
                'trainer_id' => $locked->trainer_id,
                'rescheduled_at' => now()->toIso8601String(),
            ];

            $metadata = $locked->metadata ?? [];
            $metadata['reschedules'] = [...($metadata['reschedules'] ?? []), $previous];

            $locked->fill([
                'date' => $quote->date->toDateString(),
                'end_date' => $quote->endDate?->toDateString(),
                'start_time' => $quote->startTime,
                'end_time' => $quote->endTime,
                'trainer_id' => $quote->trainerId,
                'metadata' => $metadata,
            ])->save();

            DB::afterCommit(static function () use ($locked): void {
                event(new ReservationRescheduled($locked));
            });

            return $locked;
        });
    }

    /**
     * @param  array<int, ReservationStatus>  $from
     * @param  class-string  $eventClass
     * @param  Closure(Reservation): void|null  $beforeSave
     *
     * @throws ReservationNotAllowedException
     */
    private function transition(
        Reservation $reservation,
        array $from,
        ReservationStatus $to,
        string $action,
        string $eventClass,
        ?Closure $beforeSave = null,
    ): Reservation {
        return DB::transaction(function () use ($reservation, $from, $to, $action, $eventClass, $beforeSave): Reservation {
            $locked = $this->lock($reservation);
            $this->ensureStatus($locked, $from, $action);

            if ($beforeSave !== null) {
                $beforeSave($locked);
            }

            $locked->status = $to;
            $locked->save();

            DB::afterCommit(static function () use ($locked, $eventClass): void {
                event(new $eventClass($locked));
            });

            return $locked;
        });
    }

    private function lock(Reservation $reservation): Reservation
    {
        return Reservation::query()
            ->whereKey($reservation->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array<int, ReservationStatus>  $allowed
     *
     * @throws ReservationNotAllowedException
     */
    private function ensureStatus(Reservation $reservation, array $allowed, string $action): void
    {
        if (! in_array($reservation->status, $allowed, true)) {
            throw ReservationNotAllowedException::because('invalid_transition', [
                'status' => $reservation->status->value,
                'action' => $action,
            ]);
        }
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function ensureBeforeClientCutoff(Reservation $reservation): void
    {
        $hours = (int) config('reservations.client_cancellation_cutoff_hours');

        if (now()->addHours($hours)->gt($this->startsAt($reservation))) {
            throw ReservationNotAllowedException::because('cancellation_cutoff_passed', ['hours' => $hours]);
        }
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function ensureStarted(Reservation $reservation): void
    {
        if ($this->startsAt($reservation)->isFuture()) {
            throw ReservationNotAllowedException::because('not_started_yet');
        }
    }

    private function startsAt(Reservation $reservation): CarbonImmutable
    {
        return CarbonImmutable::parse($reservation->date->toDateString().' '.($reservation->start_time ?? '00:00:00'));
    }

    private function restorePackageCredit(Reservation $reservation): void
    {
        if ($reservation->client_package_id === null) {
            return;
        }

        $package = ClientPackage::query()
            ->whereKey($reservation->client_package_id)
            ->lockForUpdate()
            ->first();

        if ($package === null) {
            return;
        }

        $package->remaining_sessions = min($package->total_sessions, $package->remaining_sessions + 1);

        if ($package->status === ClientPackageStatus::Exhausted) {
            $package->status = $package->isExpired() ? ClientPackageStatus::Expired : ClientPackageStatus::Active;
        }

        $package->save();
    }

    /**
     * Refunds the reservation's share of a confirmed purchase. Online payments
     * go back through their gateway and are recorded on the payment (which
     * becomes `refunded` once fully returned); a manual payment is flagged for
     * the center to settle by hand. Nothing was taken yet for pending or cash
     * payments.
     *
     * @return array{amount: float, payment_id: int, method: string, status: string}|null
     *
     * @throws ReservationNotAllowedException
     */
    private function refund(Reservation $reservation): ?array
    {
        /** @var PurchaseItem|null $item */
        $item = $reservation->purchaseItems()->with('purchase.payments')->latest('id')->first();

        if ($item === null || $item->purchase->status !== PurchaseStatus::Confirmed) {
            return null;
        }

        /** @var Payment|null $payment */
        $payment = $item->purchase->payments
            ->first(fn (Payment $payment): bool => $payment->status === PaymentStatus::Succeeded);

        if ($payment === null) {
            return null;
        }

        $amount = min((float) $item->total_amount, $payment->refundableAmount());

        if ($amount <= 0) {
            return null;
        }

        $refund = [
            'amount' => $amount,
            'payment_id' => $payment->getKey(),
            'method' => $payment->method->value,
            'status' => 'manual_required',
        ];

        if ($payment->method !== PaymentMethod::Online) {
            return $refund;
        }

        try {
            $refunded = $this->gateways->driver($payment->payment_company ?: null)->refund($payment, $amount);
        } catch (Throwable $exception) {
            throw ReservationNotAllowedException::because('refund_failed', previous: $exception);
        }

        if (! $refunded) {
            throw ReservationNotAllowedException::because('refund_failed');
        }

        $this->paymentState->recordRefund($payment, $amount);

        return [...$refund, 'status' => 'refunded'];
    }
}
