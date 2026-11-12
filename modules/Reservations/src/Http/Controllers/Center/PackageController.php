<?php

namespace Modules\Reservations\Http\Controllers\Center;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Reservations\Actions\AddPackageSessions;
use Modules\Reservations\Actions\BookFromPackage;
use Modules\Reservations\Actions\CancelClientPackage;
use Modules\Reservations\Actions\UpdateClientPackage;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Http\Requests\Center\AddPackageSessionsRequest;
use Modules\Reservations\Http\Requests\Center\BookFromPackageRequest;
use Modules\Reservations\Http\Requests\Center\ListClientPackagesRequest;
use Modules\Reservations\Http\Requests\Center\UpdateClientPackageRequest;
use Modules\Reservations\Http\Resources\CenterClientPackageDetailsResource;
use Modules\Reservations\Http\Resources\CenterClientPackageResource;
use Modules\Reservations\Http\Resources\CenterReservationResource;
use Modules\Reservations\Http\Resources\PurchaseSummaryResource;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client packages', 'Session packages clients bought for a center\'s services. Unpaid (`pending`) packages are never listed or counted. Served on `centers/{center}/…` (center-scoped token) and `admin/centers/{center}/…` (system user).')]
class PackageController extends Controller
{
    public function __construct(
        private readonly UpdateClientPackage $updateClientPackage,
        private readonly AddPackageSessions $addPackageSessions,
        private readonly BookFromPackage $bookFromPackage,
        private readonly CancelClientPackage $cancelClientPackage,
    ) {}

    #[Endpoint('List client packages', 'Newest first, 25 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterClientPackageResource::class, ClientPackage::class, status: 200, description: 'Paginated packages.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    public function index(ListClientPackagesRequest $request, Center $center): AnonymousResourceCollection
    {
        $active = $request->boolean('active');
        $notActive = $request->boolean('not_active');

        $packages = $this->baseQuery($request, $center)
            ->when($active && ! $notActive, fn (Builder $query) => $query->where('status', ClientPackageStatus::Active))
            ->when($notActive && ! $active, fn (Builder $query) => $query->where('status', '!=', ClientPackageStatus::Active))
            ->with(['client', 'service', 'priceOption', ...PurchaseSummaryResource::RELATIONS])
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return CenterClientPackageResource::collection($packages);
    }

    #[Endpoint('Show a client package', 'With its owner and the sessions booked from it.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('package', 'integer', 'Client package ID.', example: 41)]
    #[ResponseFromApiResource(CenterClientPackageDetailsResource::class, ClientPackage::class, status: 200, description: 'The package.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid package in this center.')]
    public function show(Request $request, Center $center, int $package): CenterClientPackageDetailsResource
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);

        $clientPackage = ClientPackage::query()
            ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
            ->where('status', '!=', ClientPackageStatus::Pending)
            ->with(['client', 'service', 'priceOption', ...PurchaseSummaryResource::RELATIONS, 'reservations' => fn ($query) => $query->orderBy('date')->orderBy('start_time')])
            ->findOrFail($package);

        return CenterClientPackageDetailsResource::make($clientPackage);
    }

    #[Endpoint('Update a client package', 'Marks the package `controlled` (or not), binds or frees its trainer (`trainer_id`, training packages only) and/or extends its validity (`expires_at`). Send at least one field. Extending an expired package that still has sessions brings it back to `active`.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('package', 'integer', 'Client package ID.', example: 41)]
    #[ResponseFromApiResource(CenterClientPackageDetailsResource::class, ClientPackage::class, status: 200, description: 'The updated package.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid package in this center.')]
    #[ApiResponse(['message' => 'The selected trainer does not offer this service.', 'reason' => 'invalid_trainer', 'errors' => ['reservation' => ['The selected trainer does not offer this service.']]], 422, 'Validation failed, or the change is not allowed (`reason`: invalid_trainer, package_not_trainer_bound, invalid_expiry, package_not_adjustable).')]
    public function update(UpdateClientPackageRequest $request, Center $center, int $package): CenterClientPackageDetailsResource
    {
        $clientPackage = $this->updateClientPackage->execute(
            $this->paidPackages($center)->findOrFail($package),
            $request->safe()->only(['controlled', 'trainer_id', 'expires_at']),
        );

        return $this->details($clientPackage);
    }

    #[Endpoint('Add sessions to a client package', 'Raises the total and remaining sessions. An exhausted package works again; a cancelled one cannot be changed.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('package', 'integer', 'Client package ID.', example: 41)]
    #[ResponseFromApiResource(CenterClientPackageDetailsResource::class, ClientPackage::class, status: 200, description: 'The updated package.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid package in this center.')]
    #[ApiResponse(['message' => 'This package is cancelled and cannot be changed.', 'reason' => 'package_not_adjustable', 'errors' => ['reservation' => ['This package is cancelled and cannot be changed.']]], 422, 'Validation failed, or the package is cancelled (`reason: package_not_adjustable`).')]
    public function addSessions(AddPackageSessionsRequest $request, Center $center, int $package): CenterClientPackageDetailsResource
    {
        $clientPackage = $this->addPackageSessions->execute(
            $this->paidPackages($center)->findOrFail($package),
            $request->integer('sessions'),
        );

        return $this->details($clientPackage);
    }

    #[Endpoint('Book a session from a client package', 'Spends one credit for one person on the client\'s behalf; confirmed at once. A trainer-bound package is booked with its trainer.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('package', 'integer', 'Client package ID.', example: 41)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(CenterReservationResource::class, Reservation::class, status: 201, description: 'The confirmed reservation.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid package in this center.')]
    #[ApiResponse(['message' => 'This package has no sessions left or has expired.', 'reason' => 'package_not_usable', 'errors' => ['reservation' => ['This package has no sessions left or has expired.']]], 422, 'Validation failed, or the slot cannot be booked (`reason`: package_not_usable, trainer_not_allowed, outside_schedule, capacity_exceeded, …).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function book(BookFromPackageRequest $request, Center $center, int $package): JsonResponse
    {
        $clientPackage = $this->paidPackages($center)->findOrFail($package);

        $reservation = $this->bookFromPackage->execute(
            $clientPackage,
            CarbonImmutable::parse($request->validated('date')),
            $request->validated('start_time'),
            ReservationSource::Center,
            $request->filled('trainer_id') ? (int) $request->validated('trainer_id') : null,
            $request->validated('notes'),
        );

        return CenterReservationResource::make($reservation->load(['service', 'trainer', 'client', 'priceOption', 'lines.priceOption', ...PurchaseSummaryResource::RELATIONS]))
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint('Cancel a client package', 'Only a package no money is held for (unpaid, cash, or already refunded) and that is not part of a membership. Its upcoming sessions are cancelled with it, and a purchase that sold nothing else is cancelled too. A paid package is cancelled by refunding its purchase (`POST …/purchases/{purchase}/refund`).')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('package', 'integer', 'Client package ID.', example: 41)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(CenterClientPackageDetailsResource::class, ClientPackage::class, status: 200, description: 'The cancelled package.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid package in this center.')]
    #[ApiResponse(['message' => 'This package has been paid for. Refund its purchase instead.', 'reason' => 'package_has_payment', 'errors' => ['reservation' => ['This package has been paid for. Refund its purchase instead.']]], 422, 'The package cannot be cancelled (`reason`: package_has_payment, package_membership_bound, package_not_cancellable).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function cancel(Request $request, Center $center, int $package): CenterClientPackageDetailsResource
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);

        $clientPackage = $this->cancelClientPackage->execute(
            $this->paidPackages($center)->findOrFail($package),
            $request->user(),
        );

        return $this->details($clientPackage);
    }

    #[Endpoint('Count client packages', 'Active vs. not active (exhausted, expired, cancelled), with the list filters `q`, `from`, `to`, `service_id`.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => ['active' => 12, 'not_active' => 30]], 200, 'Counts.')]
    #[ResponseField('data.active', 'integer', 'Active ones.', example: 12)]
    #[ResponseField('data.not_active', 'integer', 'All other paid statuses.', example: 30)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The from field must match the format Y-m-d.', 'errors' => ['from' => ['The from field must match the format Y-m-d.']]], 422, 'Invalid filters.')]
    public function counts(ListClientPackagesRequest $request, Center $center): JsonResponse
    {
        $active = (clone $this->baseQuery($request, $center))->where('status', ClientPackageStatus::Active)->count();
        $total = $this->baseQuery($request, $center)->count();

        return response()->json(['data' => ['active' => $active, 'not_active' => $total - $active]]);
    }

    private function details(ClientPackage $package): CenterClientPackageDetailsResource
    {
        return CenterClientPackageDetailsResource::make($package->load([
            'client', 'service', 'priceOption', ...PurchaseSummaryResource::RELATIONS,
            'reservations' => fn ($query) => $query->orderBy('date')->orderBy('start_time'),
        ]));
    }

    /**
     * @return Builder<ClientPackage>
     */
    private function paidPackages(Center $center): Builder
    {
        return ClientPackage::query()
            ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
            ->where('status', '!=', ClientPackageStatus::Pending);
    }

    /**
     * @return Builder<ClientPackage>
     */
    private function baseQuery(ListClientPackagesRequest $request, Center $center): Builder
    {
        return ClientPackage::query()
            ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
            ->where('status', '!=', ClientPackageStatus::Pending)
            ->when($request->filled('service_id'), fn (Builder $query) => $query->where('service_id', $request->integer('service_id')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.addcslashes($request->string('q')->trim()->toString(), '%_\\').'%';

                $query->whereHas('client', fn (Builder $client) => $client
                    ->where('phone_number', 'like', $term)
                    ->orWhereRaw("concat(first_name, ' ', last_name) like ?", [$term]));
            });
    }
}
