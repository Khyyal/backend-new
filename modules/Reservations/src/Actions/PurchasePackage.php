<?php

namespace Modules\Reservations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\CheckoutResult;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientPackage;
use Modules\Services\Models\ServicePackage;

/**
 * Sells a service package: a pending client package plus its purchase. The
 * package becomes active (and starts expiring) once the purchase is confirmed.
 */
class PurchasePackage
{
    public function __construct(
        private BookingGuard $guard,
        private StartCheckout $startCheckout,
    ) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(
        Client $client,
        ServicePackage $servicePackage,
        PurchaseSource $source,
        PaymentMethod $method,
        ?string $paymentCompany = null,
    ): CheckoutResult {
        if ($method === PaymentMethod::CashOnArrival) {
            throw ReservationNotAllowedException::because('cash_on_arrival_disabled');
        }

        $service = $servicePackage->service;

        $this->guard->ensureServiceOpenTo($service, $client);
        $this->guard->ensureSettingEnabled($service, 'enable_package_booking', 'package_booking_disabled');

        return DB::transaction(function () use ($client, $servicePackage, $source, $method, $paymentCompany): CheckoutResult {
            $clientPackage = ClientPackage::query()->create([
                'client_id' => $client->getKey(),
                'service_id' => $servicePackage->service_id,
                'service_package_id' => $servicePackage->getKey(),
                'price_option_id' => $servicePackage->price_option_id,
                'total_sessions' => $servicePackage->count,
                'remaining_sessions' => $servicePackage->count,
                'price' => $servicePackage->price,
                'status' => ClientPackageStatus::Pending,
            ]);

            return $this->startCheckout->execute(
                buyer: $client,
                merchant: $servicePackage->service->center,
                source: $source,
                method: $method,
                items: [[
                    'purchasable' => $clientPackage,
                    'name' => $servicePackage->service->name,
                    'unit_price' => (float) $servicePackage->price,
                    'metadata' => ['sessions' => $servicePackage->count],
                ]],
                paymentCompany: $paymentCompany,
            );
        });
    }
}
