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
use Modules\Reservations\Actions\BookFromPackage;
use Modules\Reservations\Actions\PurchasePackage;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Http\Requests\Client\BookFromPackageRequest;
use Modules\Reservations\Http\Requests\Client\PurchasePackageRequest;
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
     * **Request body:**
     * - `service_package_id`: required.
     * - `payment_company`: optional.
     */
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
        );

        /** @var ClientPackage $package */
        $package = $checkout->purchase->items->first()->purchasable;

        return ClientPackageResource::make($package->load(['service', 'priceOption']))
            ->additional(['checkout' => CheckoutResource::make($checkout)])
            ->response()
            ->setStatusCode(201);
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
