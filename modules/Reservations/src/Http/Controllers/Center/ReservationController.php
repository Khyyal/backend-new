<?php

namespace Modules\Reservations\Http\Controllers\Center;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Reservations\Actions\BookAndCheckout;
use Modules\Reservations\Actions\QuoteCheckout;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Http\Requests\CancelReservationRequest;
use Modules\Reservations\Http\Requests\Center\AttachReservationHorseRequest;
use Modules\Reservations\Http\Requests\Center\QuoteReservationRequest;
use Modules\Reservations\Http\Requests\Center\RescheduleReservationRequest;
use Modules\Reservations\Http\Requests\Center\StoreReservationRequest;
use Modules\Reservations\Http\Resources\CenterReservationResource;
use Modules\Reservations\Http\Resources\CheckoutQuoteResource;
use Modules\Reservations\Http\Resources\CheckoutResource;
use Modules\Reservations\Http\Resources\PurchaseSummaryResource;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Models\SystemUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Reservations', 'A center\'s reservations: list, book walk-ins, reschedule, cancel and record attendance. Served on `centers/{center}/…` (center-scoped token) and `admin/centers/{center}/…` (system user).')]
class ReservationController extends Controller
{
    private const array RELATIONS = ['service', 'trainer', 'horse', 'client', 'priceOption', 'lines.priceOption', ...PurchaseSummaryResource::RELATIONS];

    public function __construct(
        private readonly BookAndCheckout $bookAndCheckout,
        private readonly ReservationStateService $reservationState,
        private readonly QuoteCheckout $quoteCheckout,
    ) {}

    #[Endpoint('List reservations', '25 per page, sorted by date and start time by default.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('filter[status]', 'string', 'Exact status.', required: false, enum: ReservationStatus::class, example: 'confirmed')]
    #[QueryParam('filter[service_id]', 'integer', 'Exact service.', required: false, example: 31)]
    #[QueryParam('filter[client_id]', 'integer', 'Exact client.', required: false, example: 77)]
    #[QueryParam('filter[trainer_id]', 'integer', 'Exact trainer.', required: false, example: 6)]
    #[QueryParam('filter[date]', 'string', 'A single day, `Y-m-d`.', required: false, example: '2026-06-05')]
    #[QueryParam('filter[from]', 'string', 'From this day, `Y-m-d`.', required: false, example: '2026-06-01')]
    #[QueryParam('filter[to]', 'string', 'Up to this day, `Y-m-d`.', required: false, example: '2026-06-30')]
    #[QueryParam('sort', 'string', 'Sort order.', required: false, enum: ['date', '-date', 'created_at', '-created_at'], example: 'date')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'Paginated reservations.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        $this->authorize($request, $center);

        $reservations = QueryBuilder::for(Reservation::query()->where('center_id', $center->getKey())->with(self::RELATIONS), $request)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('service_id'),
                AllowedFilter::exact('client_id'),
                AllowedFilter::exact('trainer_id'),
                AllowedFilter::callback('date', fn (Builder $query, $value) => $query->whereDate('date', $value)),
                AllowedFilter::callback('from', fn (Builder $query, $value) => $query->whereDate('date', '>=', $value)),
                AllowedFilter::callback('to', fn (Builder $query, $value) => $query->whereDate('date', '<=', $value)),
            )
            ->allowedSorts(
                AllowedSort::callback('date', fn (Builder $query, bool $descending) => $query
                    ->orderBy('date', $descending ? 'desc' : 'asc')
                    ->orderBy('start_time', $descending ? 'desc' : 'asc')),
                'created_at',
            )
            ->defaultSort('date')
            ->paginate(25)
            ->withQueryString();

        return CenterReservationResource::collection($reservations);
    }

    #[Endpoint('Count reservations per day', 'One entry per day from `from` to `to` (count 0 when nothing is booked), counted on the start date. At most 366 days.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('from', 'string', 'First day, `Y-m-d`.', required: true, example: '2026-06-01')]
    #[QueryParam('to', 'string', 'Last day, `Y-m-d`, within 365 days of `from`.', required: true, example: '2026-06-30')]
    #[ApiResponse(['data' => [['date' => '2026-06-01', 'count' => 4], ['date' => '2026-06-02', 'count' => 0]]], 200, 'Counts per day.')]
    #[ResponseField('data[].date', 'string', 'Day, `Y-m-d`.', example: '2026-06-01')]
    #[ResponseField('data[].count', 'integer', 'Reservations starting that day.', example: 4)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The to field must be a date before or equal to 2027-06-01.', 'errors' => ['to' => ['The to field must be a date before or equal to 2027-06-01.']]], 422, 'Invalid or too long range.')]
    public function calendar(Request $request, Center $center): JsonResponse
    {
        $this->authorize($request, $center);

        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.CarbonImmutable::parse($request->input('from', 'now'))->addDays(365)->toDateString()],
        ]);

        $counts = Reservation::query()
            ->where('center_id', $center->getKey())
            ->whereDate('date', '>=', $validated['from'])
            ->whereDate('date', '<=', $validated['to'])
            ->select(DB::raw('date(date) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = collect(CarbonPeriod::create($validated['from'], $validated['to']))
            ->map(fn ($day): array => [
                'date' => $day->toDateString(),
                'count' => (int) ($counts[$day->toDateString()] ?? 0),
            ])
            ->values();

        return response()->json(['data' => $days]);
    }

    #[Endpoint('Show a reservation')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('reservation', 'integer', 'Reservation ID (of this center).', example: 311)]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'The reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation in this center.')]
    public function show(Request $request, Center $center, int $reservation): CenterReservationResource
    {
        $this->authorize($request, $center);

        return CenterReservationResource::make($this->find($center, $reservation)->load(self::RELATIONS));
    }

    #[Endpoint('Show a reservation by code', 'Looks the reservation up by its public 6-character `code` (case-insensitive), e.g. the one a client shows at the desk.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('code', 'string', 'Reservation code (of this center).', example: 'K7QX2M')]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'The reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No reservation with this code in this center.')]
    public function showByCode(Request $request, Center $center, string $code): CenterReservationResource
    {
        $this->authorize($request, $center);

        $reservation = Reservation::query()->where('center_id', $center->getKey())->whereCode($code)->firstOrFail();

        return CenterReservationResource::make($reservation->load(self::RELATIONS));
    }

    #[Endpoint('Book a walk-in reservation', 'Same booking fields as the client endpoint plus `client_id`, `payment_method` (`manual` = paid at the desk, confirmed at once) and `notes`.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ApiResponse(['data' => ['id' => 311, 'status' => 'pending', 'source' => 'client', 'center_id' => 8, 'service' => ['id' => 31, 'type' => 'recreation_riding', 'name' => ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride']], 'date' => '2026-06-05', 'start_time' => '16:00', 'end_time' => '17:00', 'quantity' => 2, 'unit_price' => 15000, 'subtotal' => 30000, 'total_amount' => 25500], 'checkout' => null], 201, 'Booked.')]
    #[ResponseField('data', 'object', 'The reservation (`CenterReservationResource`).')]
    #[ResponseField('checkout', 'object', 'The checkout (`CheckoutResource`: purchase, payment, gateway); null when nothing needs paying now.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such service in this center.')]
    #[ApiResponse(['message' => 'Not enough places left: 1 available.', 'reason' => 'capacity_exceeded', 'errors' => ['reservation' => ['Not enough places left: 1 available.']]], 422, 'Validation failed, the coupon was rejected (`code: coupon_rejected`), or a booking rule failed; `reason` is one of: service_inactive, service_type_not_bookable, client_blocked, bookings_closed, single_session_booking_disabled, package_booking_disabled, date_in_past, invalid_date_range, start_time_required, price_option_required, invalid_price_option, trainer_required, invalid_trainer, outside_schedule, slot_blocked, capacity_exceeded, outside_event_dates, event_booking_closed, single_ticket_option, day_not_priced, resort_quantity, resort_unavailable, invalid_quantity, cash_on_arrival_disabled.')]
    #[ApiResponse(['message' => 'The price has changed. Please review the new total.', 'code' => 'price_changed', 'data' => ['subtotal_amount' => 30000, 'discount_amount' => 0, 'total_amount' => 30000]], 409, '`expected_total_amount` differs from the current price (`code: price_changed`); `data` holds the new quote.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function store(StoreReservationRequest $request, Center $center): JsonResponse
    {
        $service = $center->services()->with(['serviceable', 'center'])->findOrFail($request->validated('service_id'));
        $client = Client::query()->findOrFail($request->validated('client_id'));

        [$reservation, $checkout] = $this->bookAndCheckout->execute(
            $request->toBookingRequest($service, $client),
            ReservationSource::Center,
            PaymentMethod::from($request->validated('payment_method')),
            $request->validated('payment_company'),
            $request->validated('notes'),
            $request->toDiscountInput(),
            $request->user() instanceof SystemUser ? null : PurchaseChannel::CenterDashboard,
        );

        return CenterReservationResource::make($reservation->load(self::RELATIONS))
            ->additional(['checkout' => $checkout !== null ? CheckoutResource::make($checkout) : null])
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint('Price a walk-in reservation', 'Returns the total after running offers and the coupon, without booking. Coupon attempts are limited to 10 per minute.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CheckoutQuoteResource::class, Reservation::class, status: 200, description: 'The priced quote; nothing is booked.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'Not enough places left: 1 available.', 'reason' => 'capacity_exceeded', 'errors' => ['reservation' => ['Not enough places left: 1 available.']]], 422, 'Validation failed, the coupon was rejected (`code: coupon_rejected`), or a booking rule failed; `reason` is one of: service_inactive, service_type_not_bookable, client_blocked, bookings_closed, single_session_booking_disabled, package_booking_disabled, date_in_past, invalid_date_range, start_time_required, price_option_required, invalid_price_option, trainer_required, invalid_trainer, outside_schedule, slot_blocked, capacity_exceeded, outside_event_dates, event_booking_closed, single_ticket_option, day_not_priced, resort_quantity, resort_unavailable, invalid_quantity, cash_on_arrival_disabled.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function quote(QuoteReservationRequest $request, Center $center): CheckoutQuoteResource
    {
        $service = $center->services()->with(['serviceable', 'center'])->findOrFail($request->validated('service_id'));
        $client = Client::query()->findOrFail($request->validated('client_id'));

        return CheckoutQuoteResource::make($this->quoteCheckout->forBooking(
            $request->toBookingRequest($service, $client),
            $request->validated('coupon_code'),
        ));
    }

    #[Endpoint('Reschedule a reservation', 'Moves it to another slot of the same service at the same price (`reason`: reschedule_price_changed otherwise). A slot without enough places answers 409 (`reason`: capacity_exceeded, resort_unavailable); send `force=true` to move it anyway (only the capacity check is skipped).')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('reservation', 'integer', 'Reservation ID (of this center).', example: 311)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'The moved reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `reservations.edit` for this center (system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation in this center.')]
    #[ApiResponse(['message' => 'Not enough places left: 1 available.', 'reason' => 'capacity_exceeded', 'errors' => ['reservation' => ['Not enough places left: 1 available.']]], 422, 'Validation failed, the coupon was rejected (`code: coupon_rejected`), or a booking rule failed; `reason` is one of: service_inactive, service_type_not_bookable, client_blocked, bookings_closed, single_session_booking_disabled, package_booking_disabled, date_in_past, invalid_date_range, start_time_required, price_option_required, invalid_price_option, trainer_required, invalid_trainer, outside_schedule, slot_blocked, capacity_exceeded, outside_event_dates, event_booking_closed, single_ticket_option, day_not_priced, resort_quantity, resort_unavailable, invalid_quantity, cash_on_arrival_disabled.')]
    #[ApiResponse(['message' => 'Not enough places left: 1 available.', 'reason' => 'capacity_exceeded', 'available' => 1, 'errors' => ['reservation' => ['Not enough places left: 1 available.']]], 409, 'Not enough places left in the new slot (`reason`: capacity_exceeded, resort_unavailable); resend with `force=true` to move it anyway. A request with the same `Idempotency-Key` still running also answers 409 (see below).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function reschedule(RescheduleReservationRequest $request, Center $center, int $reservation): CenterReservationResource
    {
        $endDate = $request->validated('end_date');

        $moved = $this->reservationState->reschedule(
            $this->find($center, $reservation),
            CarbonImmutable::parse($request->validated('date')),
            $request->validated('start_time'),
            $endDate !== null ? CarbonImmutable::parse($endDate) : null,
            $request->filled('trainer_id') ? (int) $request->validated('trainer_id') : null,
            $request->boolean('force'),
        );

        return CenterReservationResource::make($moved->load(self::RELATIONS));
    }

    #[Endpoint('Cancel a reservation', 'No cutoff for the center. A package session gets its credit back; an online payment is refunded through its gateway, a manual one is flagged `manual_required` (see `refund`).')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('reservation', 'integer', 'Reservation ID (of this center).', example: 311)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'The cancelled reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `reservations.edit` for this center (system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation in this center.')]
    #[ApiResponse(['message' => 'A completed reservation cannot be cancelled.', 'reason' => 'invalid_transition', 'errors' => ['reservation' => ['A completed reservation cannot be cancelled.']]], 422, 'The reservation cannot be cancelled from its status, or has not started yet (`reason`: invalid_transition, not_started_yet).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function cancel(CancelReservationRequest $request, Center $center, int $reservation): CenterReservationResource
    {
        $this->authorizeEdit($request, $center);

        $cancelled = $this->reservationState->cancel(
            $this->find($center, $reservation),
            $request->user(),
            $request->validated('reason'),
        );

        return CenterReservationResource::make($cancelled->load(self::RELATIONS));
    }

    #[Endpoint('Mark a reservation completed', 'Only a confirmed reservation that has started.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('reservation', 'integer', 'Reservation ID (of this center).', example: 311)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'The completed reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `reservations.edit` for this center (system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation in this center.')]
    #[ApiResponse(['message' => 'A completed reservation cannot be cancelled.', 'reason' => 'invalid_transition', 'errors' => ['reservation' => ['A completed reservation cannot be cancelled.']]], 422, 'The reservation cannot be completed from its status, or has not started yet (`reason`: invalid_transition, not_started_yet).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function complete(Request $request, Center $center, int $reservation): CenterReservationResource
    {
        $this->authorizeEdit($request, $center);

        $completed = $this->reservationState->complete($this->find($center, $reservation));

        return CenterReservationResource::make($completed->load(self::RELATIONS));
    }

    #[Endpoint('Mark a reservation as a no-show', 'Only a confirmed reservation that has started.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('reservation', 'integer', 'Reservation ID (of this center).', example: 311)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'The reservation, now `no_show`.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `reservations.edit` for this center (system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation in this center.')]
    #[ApiResponse(['message' => 'A completed reservation cannot be cancelled.', 'reason' => 'invalid_transition', 'errors' => ['reservation' => ['A completed reservation cannot be cancelled.']]], 422, 'The reservation cannot be marked from its status, or has not started yet (`reason`: invalid_transition, not_started_yet).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function noShow(Request $request, Center $center, int $reservation): CenterReservationResource
    {
        $this->authorizeEdit($request, $center);

        $marked = $this->reservationState->markNoShow($this->find($center, $reservation));

        return CenterReservationResource::make($marked->load(self::RELATIONS));
    }

    #[Endpoint('Attach a horse to a reservation', 'Sets the horse the reservation is for (any horse of this center), or clears it with `horse_id: null`. Works in any status; the slot, price and status are unchanged.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('reservation', 'integer', 'Reservation ID (of this center).', example: 311)]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 200, description: 'The reservation with its horse.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `reservations.edit` for this center (system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such reservation in this center.')]
    #[ApiResponse(['message' => 'The selected horse id is invalid.', 'errors' => ['horse_id' => ['The selected horse id is invalid.']]], 422, 'The horse does not exist in this center (or was deleted).')]
    public function attachHorse(AttachReservationHorseRequest $request, Center $center, int $reservation): CenterReservationResource
    {
        $model = $this->find($center, $reservation);

        $updated = DB::transaction(function () use ($model, $request): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($model->getKey());
            $locked->update(['horse_id' => $request->validated('horse_id')]);

            return $locked;
        });

        return CenterReservationResource::make($updated->load(self::RELATIONS));
    }

    private function authorize(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);
    }

    /**
     * Reschedule, cancel, complete and no-show need `reservations.edit`.
     */
    private function authorizeEdit(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('editReservations', $center);
    }

    private function find(Center $center, int $reservation): Reservation
    {
        return Reservation::query()->where('center_id', $center->getKey())->findOrFail($reservation);
    }
}
