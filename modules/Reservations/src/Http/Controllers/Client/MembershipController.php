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
use Modules\Reservations\Actions\BookVisitFromMembership;
use Modules\Reservations\Actions\PurchaseMembership;
use Modules\Reservations\Actions\QuoteCheckout;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Http\Requests\Client\BookVisitFromMembershipRequest;
use Modules\Reservations\Http\Requests\Client\PurchaseMembershipRequest;
use Modules\Reservations\Http\Requests\Client\QuoteMembershipRequest;
use Modules\Reservations\Http\Resources\CheckoutQuoteResource;
use Modules\Reservations\Http\Resources\CheckoutResource;
use Modules\Reservations\Http\Resources\ClientMembershipResource;
use Modules\Reservations\Http\Resources\ReservationResource;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Membership;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Reservations', 'Book, list and cancel reservations, and buy and spend packages and memberships.')]
class MembershipController extends Controller
{
    private const RELATIONS = ['service', 'membership', 'visitService', 'packages.service', 'packages.priceOption'];

    public function __construct(
        private readonly PurchaseMembership $purchaseMembership,
        private readonly BookVisitFromMembership $bookVisit,
        private readonly QuoteCheckout $quoteCheckout,
    ) {}

    #[Endpoint('List my memberships', 'Newest first, 15 per page, with packages, expiry and free visits left today. Spend the packages with `POST /api/v1/clients/packages/{package}/reservations`.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ClientMembershipResource::class, ClientMembership::class, status: 200, description: 'Paginated memberships.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $memberships = $this->client($request)->memberships()
            ->with(self::RELATIONS)
            ->latest()
            ->paginate(15);

        $today = CarbonImmutable::today();

        return ClientMembershipResource::collection($memberships->through(
            fn (ClientMembership $membership) => ClientMembershipResource::make($membership)
                ->withVisitsUsedToday($this->bookVisit->usedVisits($membership, $today))
        ));
    }

    #[Endpoint('Buy a membership', 'Once paid, the membership and each included package become active (each for its own validity). Opens an online checkout; pay with `checkout.gateway`. Coupon attempts are limited to 10 per minute.')]
    #[Header('X-Channel', 'khyyal_app')]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ApiResponse(['data' => ['id' => 9, 'status' => 'pending', 'membership_id' => 3, 'price' => 250000, 'duration' => 6, 'max_visits_per_day' => 1, 'expires_at' => null, 'is_usable' => false], 'checkout' => ['purchase' => ['id' => 5521, 'status' => 'pending', 'subtotal' => 30000, 'discount_amount' => 4500, 'total_amount' => 25500, 'coupon_code' => 'SUMMER25'], 'payment' => ['id' => 801, 'status' => 'pending', 'method' => 'online', 'payment_company' => 'moyasar', 'amount' => 25500], 'gateway' => ['type' => 'moyasar', 'publishable_key' => 'pk_test_xxx', 'amount_halalas' => 25500, 'currency' => 'SAR', 'given_id' => '801', 'description' => 'Khyyal purchase #5521']]], 201, 'Checkout opened.')]
    #[ResponseField('data', 'object', 'The membership (`ClientMembershipResource`), `pending` until paid.')]
    #[ResponseField('checkout', 'object', 'The checkout (`CheckoutResource`: purchase, payment, gateway); null when nothing needs paying now.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'Some packages of this membership are no longer available.', 'reason' => 'membership_package_unavailable', 'errors' => ['reservation' => ['Some packages of this membership are no longer available.']]], 422, 'Validation failed, the coupon was rejected, or the membership cannot be sold (`reason`).')]
    #[ApiResponse(['message' => 'The price has changed. Please review the new total.', 'code' => 'price_changed', 'data' => ['subtotal_amount' => 30000, 'discount_amount' => 0, 'total_amount' => 30000]], 409, '`expected_total_amount` differs from the current price (`code: price_changed`); `data` holds the new quote.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function store(PurchaseMembershipRequest $request): JsonResponse
    {
        $membership = Membership::query()->with('service.center')->findOrFail($request->validated('membership_id'));

        $checkout = $this->purchaseMembership->execute(
            $this->client($request),
            $membership,
            PurchaseSource::Client,
            PaymentMethod::Online,
            $request->validated('payment_company'),
            $request->toDiscountInput(),
            PurchaseChannel::fromClientRequest($request),
        );

        /** @var ClientMembership $clientMembership */
        $clientMembership = $checkout->purchase->items->first()->purchasable;

        return ClientMembershipResource::make($clientMembership->load(self::RELATIONS))
            ->additional(['checkout' => CheckoutResource::make($checkout)])
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint('Price a membership', 'Total after running offers and the coupon; nothing is bought.')]
    #[ResponseFromApiResource(CheckoutQuoteResource::class, Reservation::class, status: 200, description: 'The priced quote; nothing is booked.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The selected membership id is invalid.', 'errors' => ['membership_id' => ['The selected membership id is invalid.']]], 422, 'Validation failed.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function quote(QuoteMembershipRequest $request): CheckoutQuoteResource
    {
        $membership = Membership::query()->with('service.center')->findOrFail($request->validated('membership_id'));

        return CheckoutQuoteResource::make($this->quoteCheckout->forMembership(
            $this->client($request),
            $membership,
            $request->validated('coupon_code'),
        ));
    }

    #[Endpoint('Book a free visit from a membership', 'Spends `quantity` of today\'s free visits on the membership\'s visit service; free and confirmed at once.')]
    #[UrlParam('membership', 'integer', 'Client membership ID.', example: 9)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(ReservationResource::class, Reservation::class, status: 201, description: 'The confirmed visit.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such membership of yours.')]
    #[ApiResponse(['message' => 'Daily free visits exceeded: 0 left today.', 'reason' => 'membership_daily_visits_exceeded', 'errors' => ['reservation' => ['Daily free visits exceeded: 0 left today.']]], 422, 'Validation failed, or the visit cannot be booked (`reason`: membership_not_usable, membership_has_no_visits, membership_daily_visits_exceeded, …).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function bookVisit(BookVisitFromMembershipRequest $request, int $membership): JsonResponse
    {
        $clientMembership = $this->client($request)->memberships()->findOrFail($membership);

        $reservation = $this->bookVisit->execute(
            $clientMembership,
            CarbonImmutable::parse($request->validated('date')),
            $request->validated('start_time'),
            (int) $request->validated('quantity', 1),
            ReservationSource::Client,
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
