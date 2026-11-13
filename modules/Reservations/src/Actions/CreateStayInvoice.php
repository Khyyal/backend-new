<?php

namespace Modules\Reservations\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Reservations\Booking\CheckoutResult;
use Modules\Reservations\Booking\DiscountInput;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Enums\StayInvoiceKind;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Models\StayInvoice;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;

/**
 * Bills an extra charge to the client of a confirmed stabling stay and
 * checks it out at once: paid at the desk (`manual`, confirmed) or with a
 * pending cash payment. Offers and coupons never apply.
 */
class CreateStayInvoice
{
    public function __construct(private StartCheckout $startCheckout) {}

    /**
     * @param  array{kind: string, service_id?: int|null, price_option_id?: int|null, date: string, name?: string|null, description?: string|null, total_amount?: int|null}  $data
     * @return array{0: StayInvoice, 1: CheckoutResult|null}
     *
     * @throws ReservationNotAllowedException
     */
    public function execute(Reservation $stay, array $data, bool $isPaid, ?Model $createdBy = null, ?PurchaseChannel $channel = null): array
    {
        return DB::transaction(function () use ($stay, $data, $isPaid, $createdBy, $channel): array {
            $stay = Reservation::query()->with(['service', 'center', 'client'])->lockForUpdate()->findOrFail($stay->getKey());

            $this->ensureActiveStay($stay);

            $date = CarbonImmutable::parse($data['date'])->startOfDay();
            $this->ensureWithinStay($stay, $date);

            $kind = StayInvoiceKind::from($data['kind']);
            $override = isset($data['total_amount']) ? (int) $data['total_amount'] : null;

            [$service, $option] = $kind === StayInvoiceKind::Standalone
                ? [$stay->service, null]
                : $this->billedOption($stay, $kind, (int) $data['service_id'], (int) $data['price_option_id']);

            $price = $option === null ? (int) $override : (int) $option->price;
            $total = $override ?? $price;

            $invoice = StayInvoice::query()->create([
                'reservation_id' => $stay->getKey(),
                'center_id' => $stay->center_id,
                'client_id' => $stay->client_id,
                'kind' => $kind,
                'service_id' => $service->getKey(),
                'price_option_id' => $option?->getKey(),
                'date' => $date->toDateString(),
                'name' => $option === null ? $data['name'] : $this->serviceName($service, $option),
                'description' => $data['description'] ?? null,
                'price' => $price,
                'total_amount' => $total,
                'metadata' => $option !== null && $override !== null ? ['price_override' => ['calculated_total' => $price]] : null,
                'created_by_type' => $createdBy?->getMorphClass(),
                'created_by_id' => $createdBy?->getKey(),
            ]);

            if ($total <= 0) {
                return [$invoice, null];
            }

            $checkout = $this->startCheckout->execute(
                buyer: $stay->client,
                merchant: $stay->center,
                source: PurchaseSource::Center,
                method: $isPaid ? PaymentMethod::Manual : PaymentMethod::CashOnArrival,
                items: [[
                    'purchasable' => $invoice,
                    'name' => $invoice->name,
                    'unit_price' => $total,
                    'service_id' => $invoice->service_id,
                    'metadata' => ['kind' => $kind->value, 'reservation_id' => $stay->getKey()],
                ]],
                discount: new DiscountInput(applyPromotions: false),
                channel: $channel,
            );

            return [$invoice, $checkout];
        });
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function ensureActiveStay(Reservation $stay): void
    {
        if ($stay->service->type !== ServiceType::Stabling) {
            throw ReservationNotAllowedException::because('not_a_stabling_stay');
        }

        if ($stay->status !== ReservationStatus::Confirmed) {
            throw ReservationNotAllowedException::because('invoice_stay_not_active');
        }
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function ensureWithinStay(Reservation $stay, CarbonImmutable $date): void
    {
        $isInside = ! $date->isBefore(CarbonImmutable::parse($stay->date)->startOfDay())
            && ($stay->end_date === null || ! $date->isAfter(CarbonImmutable::parse($stay->end_date)->startOfDay()));

        if (! $isInside) {
            throw ReservationNotAllowedException::because('invoice_date_outside_stay');
        }
    }

    /**
     * @return array{0: Service, 1: PriceOption}
     *
     * @throws ReservationNotAllowedException
     */
    private function billedOption(Reservation $stay, StayInvoiceKind $kind, int $serviceId, int $priceOptionId): array
    {
        $service = Service::query()
            ->where('center_id', $stay->center_id)
            ->where('type', $kind->serviceType())
            ->where('status', ActivationStatus::ACTIVE)
            ->find($serviceId)
            ?? throw ReservationNotAllowedException::because('invalid_invoice_service');

        $option = $service->priceOptions()->find($priceOptionId)
            ?? throw ReservationNotAllowedException::because('invalid_price_option');

        return [$service, $option];
    }

    private function serviceName(Service $service, PriceOption $option): string
    {
        $name = $service->getTranslation('name', app()->getLocale());

        return mb_substr($option->name === null ? $name : "{$name} - {$option->name}", 0, 255);
    }
}
