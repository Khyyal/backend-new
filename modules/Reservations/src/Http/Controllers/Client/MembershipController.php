<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
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
use Modules\Services\Models\Membership;

#[Group(name: 'Client / Reservations', description: 'Book, list and cancel reservations, and buy and spend service packages. Requires a client Bearer token.')]
class MembershipController extends Controller
{
    private const RELATIONS = ['service', 'membership', 'visitService', 'packages.service', 'packages.priceOption'];

    public function __construct(
        private readonly PurchaseMembership $purchaseMembership,
        private readonly BookVisitFromMembership $bookVisit,
        private readonly QuoteCheckout $quoteCheckout,
    ) {}

    /**
     * List my memberships.
     *
     * Paginated (15 per page), newest first, with their packages, expiry and
     * the free visits left today. The packages are spent like any other
     * package (`POST /clients/packages/{package}/reservations`).
     */
    #[Response(status: 200, description: 'Paginated `ClientMembershipResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
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

    /**
     * Buy a membership.
     *
     * Opens an online checkout for the membership price. Once paid, the
     * membership and every package it includes become active: the membership
     * for its own duration, each package for its own validity.
     *
     * **Request body:**
     * - `membership_id`: required.
     * - `payment_company`: optional.
     * - `coupon_code`, `expected_total_amount`: optional, as when booking a reservation.
     */
    #[Response(status: 201, description: '`{data: ClientMembershipResource, checkout: CheckoutResource}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 422, description: 'Validation failed, or the membership cannot be sold (see `reason`).')]
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
        );

        /** @var ClientMembership $clientMembership */
        $clientMembership = $checkout->purchase->items->first()->purchasable;

        return ClientMembershipResource::make($clientMembership->load(self::RELATIONS))
            ->additional(['checkout' => CheckoutResource::make($checkout)])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Price a membership.
     *
     * **Request body:** `membership_id` (required), `coupon_code` (optional).
     * Returns the total after the running offers, without buying anything.
     */
    #[Response(status: 200, description: '`{data: CheckoutQuoteResource}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function quote(QuoteMembershipRequest $request): CheckoutQuoteResource
    {
        $membership = Membership::query()->with('service.center')->findOrFail($request->validated('membership_id'));

        return CheckoutQuoteResource::make($this->quoteCheckout->forMembership(
            $this->client($request),
            $membership,
            $request->validated('coupon_code'),
        ));
    }

    /**
     * Book a free visit from a membership.
     *
     * Spends `quantity` of the membership's free daily visits on its visit
     * service. The reservation is free and confirmed at once.
     *
     * **Request body:**
     * - `date`: required, `Y-m-d`, before the membership expires.
     * - `start_time`: `H:i`, required for specific-time visits.
     * - `quantity`: optional, default 1.
     */
    #[Response(status: 201, description: 'The confirmed `ReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such membership of this client.')]
    #[Response(status: 422, description: 'Validation failed, or the visit cannot be booked (see `reason`).')]
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
