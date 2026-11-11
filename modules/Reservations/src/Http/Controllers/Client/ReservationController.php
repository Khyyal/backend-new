<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
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
use Modules\Reservations\Actions\BookAndCheckout;
use Modules\Reservations\Actions\QuoteCheckout;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Http\Requests\CancelReservationRequest;
use Modules\Reservations\Http\Requests\Client\QuoteReservationRequest;
use Modules\Reservations\Http\Requests\Client\StoreReservationRequest;
use Modules\Reservations\Http\Resources\CheckoutQuoteResource;
use Modules\Reservations\Http\Resources\CheckoutResource;
use Modules\Reservations\Http\Resources\PurchaseSummaryResource;
use Modules\Reservations\Http\Resources\ReservationResource;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;
use Modules\Services\Models\Service;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Reservations', 'Book, list and cancel reservations, and buy and spend packages and memberships.')]
class ReservationController extends Controller
{
    private const array RELATIONS = ['service', 'trainer', 'priceOption', 'lines.priceOption', ...PurchaseSummaryResource::RELATIONS];

    public function __construct(
        private readonly BookAndCheckout $bookAndCheckout,
        private readonly ReservationStateService $reservationState,
        private readonly QuoteCheckout $quoteCheckout,
    ) {}

    #[Endpoint('List my reservations', 'Latest date first, 15 per page.')]
    #[QueryParam('status', 'string', 'Only this status.', required: false, enum: ReservationStatus::class, example: 'confirmed')]
    #[QueryParam('upcoming', 'boolean', '`1` for today onwards.', required: false, example: true)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ReservationResource::class, Reservation::class, status: 200, description: 'Paginated reservations.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'upcoming' => ['nullable', 'boolean'],
        ]);

        $reservations = $this->client($request)->reservations()
            ->with(self::RELATIONS)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->boolean('upcoming'), fn (Builder $query) => $query->whereDate('date', '>=', today()->toDateString()))
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->paginate(15);

        return ReservationResource::collection($reservations);
    }

    #[Endpoint('Show my reservation')]
    #[UrlParam('reservation', 'integer', 'Reservation ID.', example: 311)]
    #[ResponseFromApiResource(ReservationResource::class, Reservation::class, status: 200, description: 'The reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation of yours.')]
    public function show(Request $request, int $reservation): ReservationResource
    {
        return ReservationResource::make($this->find($request, $reservation)->load(self::RELATIONS));
    }

    #[Endpoint('Show my reservation by code', 'Looks one of your reservations up by its public 6-character `code` (case-insensitive).')]
    #[UrlParam('code', 'string', 'Reservation code.', example: 'K7QX2M')]
    #[ResponseFromApiResource(ReservationResource::class, Reservation::class, status: 200, description: 'The reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No reservation of yours with this code.')]
    public function showByCode(Request $request, string $code): ReservationResource
    {
        $reservation = $this->client($request)->reservations()->whereCode($code)->firstOrFail();

        return ReservationResource::make($reservation->load(self::RELATIONS));
    }

    #[Endpoint('Book a reservation', 'Books one slot and opens its checkout. Online: `pending` until paid with `checkout.gateway`. Cash on arrival: `confirmed` at once when the service allows it. Free: confirmed with `checkout` null. Coupon attempts are limited to 10 per minute.')]
    #[Header('X-Channel', 'khyyal_app')]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ApiResponse(['data' => ['id' => 311, 'status' => 'pending', 'source' => 'client', 'center_id' => 8, 'service' => ['id' => 31, 'type' => 'recreation_riding', 'name' => ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride']], 'date' => '2026-06-05', 'start_time' => '16:00', 'end_time' => '17:00', 'quantity' => 2, 'unit_price' => 15000, 'subtotal' => 30000, 'total_amount' => 25500], 'checkout' => ['purchase' => ['id' => 5521, 'status' => 'pending', 'subtotal' => 30000, 'discount_amount' => 4500, 'total_amount' => 25500, 'coupon_code' => 'SUMMER25'], 'payment' => ['id' => 801, 'status' => 'pending', 'method' => 'online', 'payment_company' => 'moyasar', 'amount' => 25500], 'gateway' => ['type' => 'moyasar', 'publishable_key' => 'pk_test_xxx', 'amount_halalas' => 25500, 'currency' => 'SAR', 'given_id' => '801', 'description' => 'Khyyal purchase #5521']]], 201, 'Booked.')]
    #[ResponseField('data', 'object', 'The reservation (`ReservationResource`).')]
    #[ResponseField('checkout', 'object', 'The checkout (`CheckoutResource`: purchase, payment, gateway); null when nothing needs paying now.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such service.')]
    #[ApiResponse(['message' => 'Not enough places left: 1 available.', 'reason' => 'capacity_exceeded', 'errors' => ['reservation' => ['Not enough places left: 1 available.']]], 422, 'Validation failed, the coupon was rejected (`code: coupon_rejected`), or a booking rule failed; `reason` is one of: service_inactive, service_type_not_bookable, client_blocked, bookings_closed, single_session_booking_disabled, package_booking_disabled, date_in_past, invalid_date_range, start_time_required, price_option_required, invalid_price_option, trainer_required, invalid_trainer, outside_schedule, slot_blocked, capacity_exceeded, outside_event_dates, event_booking_closed, single_ticket_option, day_not_priced, resort_quantity, resort_unavailable, invalid_quantity, cash_on_arrival_disabled.')]
    #[ApiResponse(['message' => 'The price has changed. Please review the new total.', 'code' => 'price_changed', 'data' => ['subtotal_amount' => 30000, 'discount_amount' => 0, 'total_amount' => 30000]], 409, '`expected_total_amount` differs from the current price (`code: price_changed`); `data` holds the new quote.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $service = Service::query()->with(['serviceable', 'center'])->findOrFail($request->validated('service_id'));

        [$reservation, $checkout] = $this->bookAndCheckout->execute(
            $request->toBookingRequest($service, $this->client($request)),
            ReservationSource::Client,
            PaymentMethod::from($request->validated('payment_method')),
            $request->validated('payment_company'),
            discount: $request->toDiscountInput(),
            channel: PurchaseChannel::fromClientRequest($request),
        );

        return ReservationResource::make($reservation->load(self::RELATIONS))
            ->additional(['checkout' => $checkout !== null ? CheckoutResource::make($checkout) : null])
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint('Price a reservation', 'Total after running offers and the coupon; nothing is booked. Send the returned `total_amount` as `expected_total_amount` when booking.')]
    #[ResponseFromApiResource(CheckoutQuoteResource::class, Reservation::class, status: 200, description: 'The priced quote; nothing is booked.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'Not enough places left: 1 available.', 'reason' => 'capacity_exceeded', 'errors' => ['reservation' => ['Not enough places left: 1 available.']]], 422, 'Validation failed, the coupon was rejected (`code: coupon_rejected`), or a booking rule failed; `reason` is one of: service_inactive, service_type_not_bookable, client_blocked, bookings_closed, single_session_booking_disabled, package_booking_disabled, date_in_past, invalid_date_range, start_time_required, price_option_required, invalid_price_option, trainer_required, invalid_trainer, outside_schedule, slot_blocked, capacity_exceeded, outside_event_dates, event_booking_closed, single_ticket_option, day_not_priced, resort_quantity, resort_unavailable, invalid_quantity, cash_on_arrival_disabled.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function quote(QuoteReservationRequest $request): CheckoutQuoteResource
    {
        $service = Service::query()->with(['serviceable', 'center'])->findOrFail($request->validated('service_id'));

        return CheckoutQuoteResource::make($this->quoteCheckout->forBooking(
            $request->toBookingRequest($service, $this->client($request)),
            $request->validated('coupon_code'),
        ));
    }

    #[Endpoint('Cancel my reservation', 'Allowed until the cancellation cutoff before it starts. A package session gets its credit back; an online payment is refunded (see `refund`).')]
    #[UrlParam('reservation', 'integer', 'Reservation ID.', example: 311)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(ReservationResource::class, Reservation::class, status: 200, description: 'The cancelled reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation of yours.')]
    #[ApiResponse(['message' => 'Reservations can only be cancelled up to 24 hours before they start.', 'reason' => 'cancellation_cutoff_passed', 'errors' => ['reservation' => ['Reservations can only be cancelled up to 24 hours before they start.']]], 422, 'Cannot be cancelled (`reason`: invalid_transition, cancellation_cutoff_passed, refund_failed).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function cancel(CancelReservationRequest $request, int $reservation): ReservationResource
    {
        $cancelled = $this->reservationState->cancel(
            $this->find($request, $reservation),
            $this->client($request),
            $request->validated('reason'),
        );

        return ReservationResource::make($cancelled->load(self::RELATIONS));
    }

    private function client(Request $request): Client
    {
        /** @var Client */
        return $request->user('client');
    }

    private function find(Request $request, int $reservation): Reservation
    {
        return $this->client($request)->reservations()->findOrFail($reservation);
    }
}
