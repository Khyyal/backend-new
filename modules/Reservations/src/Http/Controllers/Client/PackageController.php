<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
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
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\ServicePackage;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Reservations', 'Book, list and cancel reservations, and buy and spend packages and memberships.')]
class PackageController extends Controller
{
    public function __construct(
        private readonly PurchasePackage $purchasePackage,
        private readonly BookFromPackage $bookFromPackage,
        private readonly QuoteCheckout $quoteCheckout,
    ) {}

    #[Endpoint('List my packages', 'Newest first, 15 per page.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ClientPackageResource::class, ClientPackage::class, status: 200, description: 'Paginated packages.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $packages = $this->client($request)->packages()
            ->with(['service', 'priceOption'])
            ->latest()
            ->paginate(15);

        return ClientPackageResource::collection($packages);
    }

    #[Endpoint('Buy a package', 'The package becomes active once paid; its validity starts then. Opens an online checkout; pay with `checkout.gateway`. Coupon attempts are limited to 10 per minute.')]
    #[Header('X-Channel', 'khyyal_app')]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ApiResponse(['data' => ['id' => 41, 'status' => 'pending', 'service_package_id' => 5, 'total_sessions' => 8, 'remaining_sessions' => 8, 'price' => 100000, 'expires_at' => null, 'is_usable' => false], 'checkout' => ['purchase' => ['id' => 5521, 'status' => 'pending', 'subtotal' => 30000, 'discount_amount' => 4500, 'total_amount' => 25500, 'coupon_code' => 'SUMMER25'], 'payment' => ['id' => 801, 'status' => 'pending', 'method' => 'online', 'payment_company' => 'moyasar', 'amount' => 25500], 'gateway' => ['type' => 'moyasar', 'publishable_key' => 'pk_test_xxx', 'amount_halalas' => 25500, 'currency' => 'SAR', 'given_id' => '801', 'description' => 'Khyyal purchase #5521']]], 201, 'Checkout opened.')]
    #[ResponseField('data', 'object', 'The package (`ClientPackageResource`), `pending` until paid.')]
    #[ResponseField('checkout', 'object', 'The checkout (`CheckoutResource`: purchase, payment, gateway); null when nothing needs paying now.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'Packages cannot be used for this service.', 'reason' => 'package_not_supported', 'errors' => ['reservation' => ['Packages cannot be used for this service.']]], 422, 'Validation failed, the coupon was rejected, or the package cannot be sold (`reason`).')]
    #[ApiResponse(['message' => 'The price has changed. Please review the new total.', 'code' => 'price_changed', 'data' => ['subtotal_amount' => 30000, 'discount_amount' => 0, 'total_amount' => 30000]], 409, '`expected_total_amount` differs from the current price (`code: price_changed`); `data` holds the new quote.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
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

    #[Endpoint('Price a package', 'Total after running offers and the coupon; nothing is bought.')]
    #[ResponseFromApiResource(CheckoutQuoteResource::class, Reservation::class, status: 200, description: 'The priced quote; nothing is booked.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The selected service package id is invalid.', 'errors' => ['service_package_id' => ['The selected service package id is invalid.']]], 422, 'Validation failed.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function quote(QuotePackageRequest $request): CheckoutQuoteResource
    {
        $servicePackage = ServicePackage::query()->with('service.center')->findOrFail($request->validated('service_package_id'));

        return CheckoutQuoteResource::make($this->quoteCheckout->forPackage(
            $this->client($request),
            $servicePackage,
            $request->validated('coupon_code'),
        ));
    }

    #[Endpoint('Book a session from a package', 'Uses one credit for one person; confirmed at once.')]
    #[UrlParam('package', 'integer', 'Client package ID.', example: 41)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(ReservationResource::class, Reservation::class, status: 201, description: 'The confirmed reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such package of yours.')]
    #[ApiResponse(['message' => 'This package has no sessions left or has expired.', 'reason' => 'package_not_usable', 'errors' => ['reservation' => ['This package has no sessions left or has expired.']]], 422, 'Validation failed, or the slot cannot be booked (`reason`: package_not_usable, package_mismatch, outside_schedule, capacity_exceeded, …).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
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

        return ReservationResource::make($reservation->load(['service', 'trainer', 'priceOption', 'lines.priceOption']))
            ->response()
            ->setStatusCode(201);
    }

    private function client(Request $request): Client
    {
        /** @var Client */
        return $request->user('client');
    }
}
