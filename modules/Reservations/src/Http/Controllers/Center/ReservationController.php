<?php

namespace Modules\Reservations\Http\Controllers\Center;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Reservations\Actions\BookAndCheckout;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Http\Requests\CancelReservationRequest;
use Modules\Reservations\Http\Requests\Center\RescheduleReservationRequest;
use Modules\Reservations\Http\Requests\Center\StoreReservationRequest;
use Modules\Reservations\Http\Resources\CenterReservationResource;
use Modules\Reservations\Http\Resources\CheckoutResource;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

#[Group(name: 'Center / Reservations', description: 'The center\'s reservations: list them, book walk-ins, reschedule, cancel and record attendance. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ReservationController extends Controller
{
    private const array RELATIONS = ['service', 'trainer', 'client', 'lines.priceOption'];

    public function __construct(
        private readonly BookAndCheckout $bookAndCheckout,
        private readonly ReservationStateService $reservationState,
    ) {}

    /**
     * List the center's reservations.
     *
     * Paginated (25 per page). Sorted by date and start time by default.
     *
     * **Query:**
     * - `filter[status]`, `filter[service_id]`, `filter[client_id]`, `filter[trainer_id]`: exact match.
     * - `filter[date]`: a single day.
     * - `filter[from]` / `filter[to]`: an inclusive date range.
     * - `sort`: `date`, `-date`, `created_at` or `-created_at`.
     */
    #[Response(status: 200, description: 'Paginated `CenterReservationResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
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

    /**
     * Show a reservation.
     */
    #[Response(status: 200, description: 'The `CenterReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 404, description: 'No such reservation in this center.')]
    public function show(Request $request, Center $center, int $reservation): CenterReservationResource
    {
        $this->authorize($request, $center);

        return CenterReservationResource::make($this->find($center, $reservation)->load(self::RELATIONS));
    }

    /**
     * Book a walk-in reservation for a client.
     *
     * Takes the same booking fields as the client endpoint, plus:
     * - `client_id`: required.
     * - `payment_method`: `manual` (paid at the desk, confirmed at once), `cash_on_arrival` or `online`.
     * - `notes`: optional staff notes.
     */
    #[Response(status: 201, description: '`{data: CenterReservationResource, checkout: CheckoutResource|null}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    #[Response(status: 422, description: 'Validation failed, or the slot cannot be booked (see `reason`).')]
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
        );

        return CenterReservationResource::make($reservation->load(self::RELATIONS))
            ->additional(['checkout' => $checkout !== null ? CheckoutResource::make($checkout) : null])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Reschedule a reservation.
     *
     * Moves it to another slot of the same service. The new slot must be free and cost the same.
     *
     * **Request body:** `date` (required), `start_time`, `end_date`, `trainer_id`.
     */
    #[Response(status: 200, description: 'The moved `CenterReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 404, description: 'No such reservation in this center.')]
    #[Response(status: 422, description: 'Validation failed, or the reservation cannot move there (see `reason`).')]
    public function reschedule(RescheduleReservationRequest $request, Center $center, int $reservation): CenterReservationResource
    {
        $endDate = $request->validated('end_date');

        $moved = $this->reservationState->reschedule(
            $this->find($center, $reservation),
            CarbonImmutable::parse($request->validated('date')),
            $request->validated('start_time'),
            $endDate !== null ? CarbonImmutable::parse($endDate) : null,
            $request->filled('trainer_id') ? (int) $request->validated('trainer_id') : null,
        );

        return CenterReservationResource::make($moved->load(self::RELATIONS));
    }

    /**
     * Cancel a reservation.
     *
     * There is no cutoff for the center. A package session gets its credit back. An online payment is
     * refunded through its gateway; a manual payment is flagged `manual_required`.
     *
     * **Request body:** `reason`, optional.
     */
    #[Response(status: 200, description: 'The cancelled `CenterReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 404, description: 'No such reservation in this center.')]
    #[Response(status: 422, description: 'The reservation cannot be cancelled (see `reason`).')]
    public function cancel(CancelReservationRequest $request, Center $center, int $reservation): CenterReservationResource
    {
        $cancelled = $this->reservationState->cancel(
            $this->find($center, $reservation),
            $request->user(),
            $request->validated('reason'),
        );

        return CenterReservationResource::make($cancelled->load(self::RELATIONS));
    }

    /**
     * Mark a reservation as completed.
     *
     * Only a confirmed reservation that has already started.
     */
    #[Response(status: 200, description: 'The completed `CenterReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 404, description: 'No such reservation in this center.')]
    #[Response(status: 422, description: 'The reservation cannot be completed (see `reason`).')]
    public function complete(Request $request, Center $center, int $reservation): CenterReservationResource
    {
        $this->authorize($request, $center);

        $completed = $this->reservationState->complete($this->find($center, $reservation));

        return CenterReservationResource::make($completed->load(self::RELATIONS));
    }

    /**
     * Mark a reservation as a no-show.
     *
     * Only a confirmed reservation that has already started.
     */
    #[Response(status: 200, description: 'The `CenterReservationResource` marked `no_show`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 404, description: 'No such reservation in this center.')]
    #[Response(status: 422, description: 'The reservation cannot be marked (see `reason`).')]
    public function noShow(Request $request, Center $center, int $reservation): CenterReservationResource
    {
        $this->authorize($request, $center);

        $marked = $this->reservationState->markNoShow($this->find($center, $reservation));

        return CenterReservationResource::make($marked->load(self::RELATIONS));
    }

    private function authorize(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);
    }

    private function find(Center $center, int $reservation): Reservation
    {
        return Reservation::query()->where('center_id', $center->getKey())->findOrFail($reservation);
    }
}
