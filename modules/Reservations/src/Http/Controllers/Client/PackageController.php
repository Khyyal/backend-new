<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Reservations\Actions\BookFromPackage;
use Modules\Reservations\Actions\PurchasePackage;
use Modules\Reservations\Actions\QuoteCheckout;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Http\Requests\Client\BookFromPackageRequest;
use Modules\Reservations\Http\Requests\Client\PurchasePackageRequest;
use Modules\Reservations\Http\Requests\Client\QuotePackageRequest;
use Modules\Reservations\Http\Resources\CheckoutQuoteResource;
use Modules\Reservations\Http\Resources\CheckoutResource;
use Modules\Reservations\Http\Resources\ClientPackageResource;
use Modules\Reservations\Http\Resources\ReservationResource;
use Modules\Reservations\Models\ClientPackage;
use Modules\Services\Models\ServicePackage;

#[Group(name: 'Client / Reservations', description: 'Book, list and cancel reservations, and buy and spend service packages. Requires a client Bearer token.')]
class PackageController extends Controller
{
    public function __construct(
        private readonly PurchasePackage $purchasePackage,
        private readonly BookFromPackage $bookFromPackage,
        private readonly QuoteCheckout $quoteCheckout,
    ) {}

    /**
     * List my packages.
     *
     * Paginated (15 per page), newest first, with their remaining sessions and expiry.
     */
    #[Response(status: 200, description: 'Paginated `ClientPackageResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $packages = $this->client($request)->packages()
            ->with(['service', 'priceOption'])
            ->latest()
            ->paginate(15);

        return ClientPackageResource::collection($packages);
    }

    /**
     * Buy a package.
     *
     * Opens an online checkout. The package becomes active once paid, and its validity starts then.
     *
     * **`checkout.gateway`** is what the app needs to pay; its shape depends on `payment_company`
     * (`moyasar`, `tamara`, or the default gateway; `fake` exists only in development and tests).
     * Amounts are integer halalas. The app never decides the outcome: the status changes only
     * after the payment provider confirms the payment to the backend.
     *
     * - `moyasar` (card, paid inside the app with the Moyasar SDK, no redirect from us):
     *   `{type: "moyasar", publishable_key, amount_halalas, currency, given_id, description}`.
     *   Create the Moyasar payment with `publishable_key`, `amount_halalas` and `currency` as given, and
     *   send `given_id` unchanged as the payment's `given_id`: it is our payment id and links the
     *   Moyasar payment to this checkout. `description` is the text to show. Moyasar returns a
     *   `transaction_url` for 3-D Secure; open it when present.
     * - `tamara` (installments, paid on Tamara's page): `{checkout_url, provider_reference, checkout_id, tamara_status}`.
     *   Open `checkout_url` in a browser or web view. `provider_reference` is the Tamara order id,
     *   `checkout_id` the Tamara checkout id, `tamara_status` the order status when created (`new`).
     *   Tamara sends the customer back to the success, failure or cancel URL configured for the app.
     * - `fake` (testing only): `{checkout_url, provider_reference}`, a placeholder that never charges.
     *
     * `checkout.payment.payment_company` repeats the gateway used. `checkout.gateway` is null when no
     * online payment is needed.
     *
     * **Request body:**
     * - `service_package_id`: required.
     * - `payment_company`: optional.
     * - `coupon_code`, `expected_total_amount`: optional, as when booking a reservation.
     */
    #[HeaderParameter('X-Channel', description: 'Where the client is buying from: `khyyal_app`, `center_app`, `khyyal_web` or `center_web`. Prices the platform fee; unknown values are ignored.', type: 'string')]
    #[Response(status: 201, description: '`{data: ClientPackageResource, checkout: CheckoutResource}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 422, description: 'Validation failed, or the package cannot be sold (see `reason`).')]
    public function store(PurchasePackageRequest $request): JsonResponse
    {
        $servicePackage = ServicePackage::query()->with('service.serviceable', 'service.center')
            ->findOrFail($request->validated('service_package_id'));

        $checkout = $this->purchasePackage->execute(
            $this->client($request),
            $servicePackage,
            PurchaseSource::Client,
            PaymentMethod::Online,
            $request->validated('payment_company'),
            $request->toDiscountInput(),
            PurchaseChannel::fromClientRequest($request),
        );

        /** @var ClientPackage $package */
        $package = $checkout->purchase->items->first()->purchasable;

        return ClientPackageResource::make($package->load(['service', 'priceOption']))
            ->additional(['checkout' => CheckoutResource::make($checkout)])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Price a package.
     *
     * **Request body:** `service_package_id` (required), `coupon_code` (optional).
     * Returns the total after the running offers, without buying anything.
     */
    #[Response(status: 200, description: '`{data: CheckoutQuoteResource}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function quote(QuotePackageRequest $request): CheckoutQuoteResource
    {
        $servicePackage = ServicePackage::query()->with('service.center')->findOrFail($request->validated('service_package_id'));

        return CheckoutQuoteResource::make($this->quoteCheckout->forPackage(
            $this->client($request),
            $servicePackage,
            $request->validated('coupon_code'),
        ));
    }

    /**
     * Book a session from a package.
     *
     * Uses one credit for a single person. The reservation is confirmed at once.
     *
     * **Request body:**
     * - `date`: required, `Y-m-d`.
     * - `start_time`: required, `H:i`.
     * - `trainer_id`: for training packages.
     */
    #[Response(status: 201, description: 'The confirmed `ReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such package of this client.')]
    #[Response(status: 422, description: 'Validation failed, or the slot cannot be booked (see `reason`).')]
    public function book(BookFromPackageRequest $request, int $package): JsonResponse
    {
        $clientPackage = $this->client($request)->packages()->findOrFail($package);

        $reservation = $this->bookFromPackage->execute(
            $clientPackage,
            CarbonImmutable::parse($request->validated('date')),
            $request->validated('start_time'),
            ReservationSource::Client,
            $request->filled('trainer_id') ? (int) $request->validated('trainer_id') : null,
        );

        return ReservationResource::make($reservation->load(['service', 'trainer', 'lines.priceOption']))
            ->response()
            ->setStatusCode(201);
    }

    private function client(Request $request): Client
    {
        /** @var Client */
        return $request->user('client');
    }
}
