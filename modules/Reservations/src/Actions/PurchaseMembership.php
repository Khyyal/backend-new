<?php

namespace Modules\Reservations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\CheckoutResult;
use Modules\Reservations\Booking\DiscountInput;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientMembership;
use Modules\Services\Models\Membership;
use Modules\Services\Models\MembershipPackage;
use Modules\Support\Enums\ActivationStatus;

/**
 * Sells a membership: a pending client membership holding one pending client
 * package per included service package, bought as a single purchase item at
 * the membership price. Everything activates once the purchase is confirmed.
 */
class PurchaseMembership
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
        Membership $membership,
        PurchaseSource $source,
        PaymentMethod $method,
        ?string $paymentCompany = null,
        DiscountInput $discount = new DiscountInput,
    ): CheckoutResult {
        if ($method === PaymentMethod::CashOnArrival) {
            throw ReservationNotAllowedException::because('cash_on_arrival_disabled');
        }

        $service = $membership->service;
        $membership->loadMissing('packages.servicePackage.service');

        $this->guard->ensureServiceOpenTo($service, $client);
        $this->ensurePackagesAvailable($membership);

        $price = (int) $service->priceOptions()->value('price');

        return DB::transaction(function () use ($client, $membership, $service, $price, $source, $method, $paymentCompany, $discount): CheckoutResult {
            $clientMembership = ClientMembership::query()->create([
                'client_id' => $client->getKey(),
                'service_id' => $service->getKey(),
                'membership_id' => $membership->getKey(),
                'price' => $price,
                'status' => ClientMembershipStatus::Pending,
                'visit_service_id' => $membership->visit_service_id,
                'max_visits_per_day' => $membership->max_visits_per_day,
                'duration' => $membership->duration,
            ]);

            foreach ($membership->packages as $package) {
                $clientMembership->packages()->create([
                    'client_id' => $client->getKey(),
                    'service_id' => $package->servicePackage->service_id,
                    'service_package_id' => $package->service_package_id,
                    'price_option_id' => $package->servicePackage->price_option_id,
                    'trainer_id' => $package->trainer_id,
                    'total_sessions' => $package->servicePackage->count,
                    'remaining_sessions' => $package->servicePackage->count,
                    'price' => 0,
                    'status' => ClientPackageStatus::Pending,
                ]);
            }

            return $this->startCheckout->execute(
                buyer: $client,
                merchant: $service->center,
                source: $source,
                method: $method,
                items: [[
                    'purchasable' => $clientMembership,
                    'name' => $service->name,
                    'unit_price' => $price,
                    'service_id' => $service->getKey(),
                    'metadata' => ['packages' => $membership->packages->count()],
                ]],
                paymentCompany: $paymentCompany,
                discount: $discount,
            );
        });
    }

    /**
     * Every included package is still sold, on an active service.
     *
     * @throws ReservationNotAllowedException
     */
    private function ensurePackagesAvailable(Membership $membership): void
    {
        $unavailable = $membership->packages->isEmpty() || $membership->packages->contains(
            fn (MembershipPackage $package): bool => $package->servicePackage->trashed()
                || $package->servicePackage->service?->status !== ActivationStatus::ACTIVE
        );

        if ($unavailable) {
            throw ReservationNotAllowedException::because('membership_package_unavailable');
        }
    }
}
