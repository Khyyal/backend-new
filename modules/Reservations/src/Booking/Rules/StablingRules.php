<?php

namespace Modules\Reservations\Booking\Rules;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\BookingQuote;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\ReservationRules;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Models\Stabling;

/**
 * A stabling stay of one horse, booked by the center. A monthly price covers
 * its months from the start date (inclusive end); the daily price opens an
 * open-ended stay (no end date) that lasts until it is completed or
 * cancelled. Extras are added once per stay. The horse must not have another
 * pending or confirmed stabling stay overlapping this one.
 */
class StablingRules implements ReservationRules
{
    public function __construct(private BookingGuard $guard) {}

    public function quote(BookingRequest $request): BookingQuote
    {
        if ($request->isReschedule()) {
            throw ReservationNotAllowedException::because('stabling_reschedule_not_supported');
        }

        if ($request->usesPackage()) {
            throw ReservationNotAllowedException::because('package_not_supported');
        }

        if ($request->horseId === null) {
            throw ReservationNotAllowedException::because('horse_required');
        }

        if ($request->quantity !== 1) {
            throw ReservationNotAllowedException::because('invalid_quantity');
        }

        $this->guard->ensureBookable($request);

        $startDate = $request->date->startOfDay();
        $this->guard->ensureNotInPast($startDate);

        /** @var Stabling $stabling */
        $stabling = $request->service->serviceable;
        $option = $this->durationOption($request, $stabling);
        $endDate = $option->unit === PriceOptionUnit::MONTH
            ? $startDate->addMonthsNoOverflow((int) $option->quantity)->subDay()
            : null;

        $this->ensureNotBlocked($request, $startDate, $endDate);
        $this->ensureHorseFree($request, $startDate, $endDate);

        $lines = $this->extraLines($request, $stabling);
        $unitPrice = (int) $option->price;

        return new BookingQuote(
            date: $startDate,
            quantity: 1,
            unitPrice: $unitPrice,
            subtotal: $unitPrice + array_sum(array_column($lines, 'subtotal')),
            endDate: $endDate,
            priceOptionId: $option->getKey(),
            lines: $lines,
            horseId: $request->horseId,
        );
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function durationOption(BookingRequest $request, Stabling $stabling): PriceOption
    {
        $option = $request->priceOptionId === null ? null : $stabling->basePrices()->find($request->priceOptionId);

        if ($option === null) {
            throw ReservationNotAllowedException::because($request->priceOptionId === null ? 'price_option_required' : 'invalid_price_option');
        }

        return $option;
    }

    /**
     * An all-day block over the stay closes it; an open-ended stay only
     * checks its first day.
     *
     * @throws ReservationNotAllowedException
     */
    private function ensureNotBlocked(BookingRequest $request, CarbonImmutable $startDate, ?CarbonImmutable $endDate): void
    {
        $blocked = ServiceBlock::query()
            ->forService($request->service)
            ->where('time_scope', BlockTimeScope::AllDay)
            ->whereDate('start_date', '<=', ($endDate ?? $startDate)->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->exists();

        if ($blocked) {
            throw ReservationNotAllowedException::because('slot_blocked');
        }
    }

    /**
     * The horse is busy while another pending or confirmed stabling stay
     * overlaps this one; a stay without end date runs indefinitely.
     *
     * @throws ReservationNotAllowedException
     */
    private function ensureHorseFree(BookingRequest $request, CarbonImmutable $startDate, ?CarbonImmutable $endDate): void
    {
        $busy = Reservation::query()
            ->where('horse_id', $request->horseId)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereHas('service', fn (Builder $query) => $query->where('type', ServiceType::Stabling))
            ->when($endDate !== null, fn (Builder $query) => $query->whereDate('date', '<=', $endDate->toDateString()))
            ->where(fn (Builder $query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $startDate->toDateString()))
            ->when($request->ignoreReservationId !== null, fn (Builder $query) => $query->whereKeyNot($request->ignoreReservationId))
            ->exists();

        if ($busy) {
            throw ReservationNotAllowedException::because('horse_unavailable');
        }
    }

    /**
     * @return array<int, array{price_option_id: int, kind: ReservationLineKind, quantity: int, unit_price: int, subtotal: int}>
     *
     * @throws ReservationNotAllowedException
     */
    private function extraLines(BookingRequest $request, Stabling $stabling): array
    {
        if ($request->lines === []) {
            return [];
        }

        $extras = $stabling->extras()->get()->keyBy('id');

        return array_values(array_map(function (array $line) use ($extras): array {
            $extra = $extras->get($line['price_option_id'])
                ?? throw ReservationNotAllowedException::because('invalid_price_option');
            $price = (int) $extra->price;

            return [
                'price_option_id' => $extra->getKey(),
                'kind' => ReservationLineKind::Addon,
                'quantity' => 1,
                'unit_price' => $price,
                'subtotal' => $price,
            ];
        }, $request->lines));
    }
}
