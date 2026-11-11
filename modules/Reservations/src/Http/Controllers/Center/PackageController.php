<?php

namespace Modules\Reservations\Http\Controllers\Center;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Http\Requests\Center\ListClientPackagesRequest;
use Modules\Reservations\Http\Requests\Center\UpdateClientPackageRequest;
use Modules\Reservations\Http\Resources\CenterClientPackageDetailsResource;
use Modules\Reservations\Http\Resources\CenterClientPackageResource;
use Modules\Reservations\Models\ClientPackage;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client packages', 'Session packages clients bought for a center\'s services. Unpaid (`pending`) packages are never listed or counted. Served on `centers/{center}/…` (center-scoped token) and `admin/centers/{center}/…` (system user).')]
class PackageController extends Controller
{
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
            ->with(['client', 'service', 'priceOption'])
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
            ->with(['client', 'service', 'priceOption', 'reservations' => fn ($query) => $query->orderBy('date')->orderBy('start_time')])
            ->findOrFail($package);

        return CenterClientPackageDetailsResource::make($clientPackage);
    }

    #[Endpoint('Update a client package', 'Marks the package as `controlled` (or not) by the center.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('package', 'integer', 'Client package ID.', example: 41)]
    #[ResponseFromApiResource(CenterClientPackageDetailsResource::class, ClientPackage::class, status: 200, description: 'The updated package.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid package in this center.')]
    #[ApiResponse(['message' => 'The controlled field is required.', 'errors' => ['controlled' => ['The controlled field is required.']]], 422, 'Validation failed.')]
    public function update(UpdateClientPackageRequest $request, Center $center, int $package): CenterClientPackageDetailsResource
    {
        $clientPackage = $this->paidPackages($center)->findOrFail($package);

        $clientPackage->update(['controlled' => $request->boolean('controlled')]);

        return CenterClientPackageDetailsResource::make($clientPackage->load([
            'client', 'service', 'priceOption',
            'reservations' => fn ($query) => $query->orderBy('date')->orderBy('start_time'),
        ]));
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
