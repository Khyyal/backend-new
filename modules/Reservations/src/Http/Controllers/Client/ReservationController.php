<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Reservations\Actions\BookAndCheckout;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Http\Requests\CancelReservationRequest;
use Modules\Reservations\Http\Requests\Client\StoreReservationRequest;
use Modules\Reservations\Http\Resources\CheckoutResource;
use Modules\Reservations\Http\Resources\ReservationResource;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;
use Modules\Services\Models\Service;

#[Group(name: 'Client / Reservations', description: 'Book, list and cancel reservations, and buy and spend service packages. Requires a client Bearer token.')]
class ReservationController extends Controller
{
    private const array RELATIONS = ['service', 'trainer', 'lines.priceOption'];

    public function __construct(
        private readonly BookAndCheckout $bookAndCheckout,
        private readonly ReservationStateService $reservationState,
    ) {}

    /**
     * List my reservations.
     *
     * Paginated (15 per page), latest date first.
     *
     * **Query:** `status` (one of the reservation statuses), `upcoming` (`1` for today onwards).
     */
    #[Response(status: 200, description: 'Paginated `ReservationResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
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

    /**
     * Show one of my reservations.
     */
    #[Response(status: 200, description: 'The `ReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such reservation of this client.')]
    public function show(Request $request, int $reservation): ReservationResource
    {
        return ReservationResource::make($this->find($request, $reservation)->load(self::RELATIONS));
    }

    /**
     * Book a reservation.
     *
     * Books one slot and opens its checkout in one step.
     *
     * - **Online:** stays `pending` until paid. Pay with `checkout.gateway`.
     * - **Cash on arrival:** `confirmed` at once, when the service allows it.
     * - **Free:** confirmed with no checkout (`checkout` is null).
     *
     * **Request body:**
     * - `service_id`: required.
     * - `date`: required, `Y-m-d`.
     * - `start_time`: `H:i`, for riding, training and timed visits.
     * - `end_date`: last day of a resort stay.
     * - `quantity`: default 1.
     * - `price_option_id`: the duration, for riding and training.
     * - `trainer_id`: for training.
     * - `lines`: `[{price_option_id, quantity}]`. Exactly one ticket option for events; any number of add-ons for resorts.
     * - `payment_method`: `online` or `cash_on_arrival`.
     * - `payment_company`: optional, for online payments.
     */
    #[Response(status: 201, description: '`{data: ReservationResource, checkout: CheckoutResource|null}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such service.')]
    #[Response(status: 422, description: 'Validation failed, or the slot cannot be booked (see `reason`).')]
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $service = Service::query()->with(['serviceable', 'center'])->findOrFail($request->validated('service_id'));

        [$reservation, $checkout] = $this->bookAndCheckout->execute(
            $request->toBookingRequest($service, $this->client($request)),
            ReservationSource::Client,
            PaymentMethod::from($request->validated('payment_method')),
            $request->validated('payment_company'),
        );

        return ReservationResource::make($reservation->load(self::RELATIONS))
            ->additional(['checkout' => $checkout !== null ? CheckoutResource::make($checkout) : null])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Cancel one of my reservations.
     *
     * Allowed until the cancellation cutoff before the reservation starts. A package session gets its
     * credit back; an online payment is refunded (the refund outcome is in `refund`).
     *
     * **Request body:** `reason`, optional.
     */
    #[Response(status: 200, description: 'The cancelled `ReservationResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such reservation of this client.')]
    #[Response(status: 422, description: 'The reservation cannot be cancelled (see `reason`).')]
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
