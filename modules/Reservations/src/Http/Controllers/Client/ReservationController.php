<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
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
        private readonly QuoteCheckout $quoteCheckout,
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
     * - `coupon_code`: optional; a code that cannot be applied fails the booking (422, `code: coupon_rejected`).
     * - `expected_total_amount`: optional, the quoted total; a different price fails the booking (409, `code: price_changed`).
     */
    #[HeaderParameter('X-Channel', description: 'Where the client is buying from: `khyyal_app`, `center_app`, `khyyal_web` or `center_web`. Prices the platform fee; unknown values are ignored.', type: 'string')]
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
            discount: $request->toDiscountInput(),
            channel: PurchaseChannel::fromClientRequest($request),
        );

        return ReservationResource::make($reservation->load(self::RELATIONS))
            ->additional(['checkout' => $checkout !== null ? CheckoutResource::make($checkout) : null])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Price a reservation.
     *
     * Takes the booking fields of `store` plus an optional `coupon_code`, and
     * returns the total after the running offers, without booking anything.
     * Send the returned `total_amount` as `expected_total_amount` when booking
     * to be refused (409) rather than charged a different price.
     */
    #[Response(status: 200, description: '`{data: CheckoutQuoteResource}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 422, description: 'Validation failed, or the slot cannot be booked (see `reason`).')]
    public function quote(QuoteReservationRequest $request): CheckoutQuoteResource
    {
        $service = Service::query()->with(['serviceable', 'center'])->findOrFail($request->validated('service_id'));

        return CheckoutQuoteResource::make($this->quoteCheckout->forBooking(
            $request->toBookingRequest($service, $this->client($request)),
            $request->validated('coupon_code'),
        ));
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
