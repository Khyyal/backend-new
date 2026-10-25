<?php

namespace Modules\Reservations\Booking\Rules;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\BookingQuote;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\ReservationRules;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Resort;

/**
 * A resort is reserved as a whole for an inclusive range of days. Each day is
 * priced by its weekday price; addons are added once per stay.
 */
class ResortRules implements ReservationRules
{
    public function __construct(private BookingGuard $guard) {}

    public function quote(BookingRequest $request): BookingQuote
    {
        if ($request->usesPackage()) {
            throw ReservationNotAllowedException::because('package_not_supported');
        }

        if ($request->quantity !== 1) {
            throw ReservationNotAllowedException::because('resort_quantity');
        }

        $this->guard->ensureBookable($request);

        $startDate = $request->date->startOfDay();
        $endDate = ($request->endDate ?? $request->date)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw ReservationNotAllowedException::because('invalid_date_range');
        }

        $this->guard->ensureNotInPast($startDate);

        /** @var Resort $resort */
        $resort = $request->service->serviceable;
        $dayPrices = $resort->dayPrices()->get()->keyBy(fn ($option): int => (int) $option->quantity);

        $daysTotal = 0.0;

        for ($day = $startDate; $day->lte($endDate); $day = $day->addDay()) {
            $price = $dayPrices->get($day->dayOfWeek)
                ?? throw ReservationNotAllowedException::because('day_not_priced', ['date' => $day->toDateString()]);

            $this->guard->ensureNotBlocked($request->service, $day);

            $daysTotal += (float) $price->price;
        }

        $this->ensureFree($request, $startDate, $endDate);

        $lines = $this->addonLines($request, $resort);
        $addonsTotal = array_sum(array_column($lines, 'subtotal'));

        return new BookingQuote(
            date: $startDate,
            quantity: 1,
            unitPrice: round($daysTotal, 2),
            subtotal: round($daysTotal + $addonsTotal, 2),
            endDate: $endDate,
            lines: $lines,
        );
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function ensureFree(BookingRequest $request, CarbonImmutable $startDate, CarbonImmutable $endDate): void
    {
        $taken = Reservation::query()
            ->occupyingCapacity()
            ->where('service_id', $request->service->getKey())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereDate('end_date', '>=', $startDate->toDateString())
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('end_date')
                    ->whereDate('date', '>=', $startDate->toDateString())))
            ->when($request->ignoreReservationId !== null, fn (Builder $query) => $query->whereKeyNot($request->ignoreReservationId))
            ->exists();

        if ($taken) {
            throw ReservationNotAllowedException::because('resort_unavailable');
        }
    }

    /**
     * @return array<int, array{price_option_id: int, kind: ReservationLineKind, quantity: int, unit_price: float, subtotal: float}>
     *
     * @throws ReservationNotAllowedException
     */
    private function addonLines(BookingRequest $request, Resort $resort): array
    {
        if ($request->lines === []) {
            return [];
        }

        $addons = $resort->addons()->get()->keyBy('id');

        return array_values(array_map(function (array $line) use ($addons): array {
            $addon = $addons->get($line['price_option_id'])
                ?? throw ReservationNotAllowedException::because('invalid_price_option');
            $quantity = (int) $line['quantity'];

            if ($quantity < 1) {
                throw ReservationNotAllowedException::because('invalid_quantity');
            }

            $unitPrice = (float) $addon->price;

            return [
                'price_option_id' => $addon->getKey(),
                'kind' => ReservationLineKind::Addon,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($unitPrice * $quantity, 2),
            ];
        }, $request->lines));
    }
}
