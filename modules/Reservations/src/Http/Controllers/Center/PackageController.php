<?php

namespace Modules\Reservations\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Centers\Models\Center;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Http\Requests\Center\ListClientPackagesRequest;
use Modules\Reservations\Http\Resources\CenterClientPackageResource;
use Modules\Reservations\Models\ClientPackage;

#[Group(name: 'Center / Client packages', description: 'The packages clients bought for the center\'s services. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class PackageController extends Controller
{
    /**
     * List the center's client packages.
     *
     * Paginated (25 per page), newest first. Unpaid (`pending`) packages are never listed.
     *
     * **Query:**
     * - `active`: `1` keeps only active packages.
     * - `not_active`: `1` keeps only the other statuses (exhausted, expired, cancelled). With both flags set, all of them are returned.
     * - `q`: search in the client's name or phone number.
     * - `from` / `to`: inclusive range on the purchase date (`Y-m-d`).
     * - `service_id`: packages of one service.
     */
    #[Response(status: 200, description: 'Paginated `CenterClientPackageResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 422, description: 'Invalid filters.')]
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

    /**
     * Count the center's client packages.
     *
     * Returns how many are active and how many are not (exhausted, expired or cancelled). Unpaid
     * (`pending`) packages are never counted. Takes the same `q`, `from`, `to` and `service_id` filters as the list.
     */
    #[Response(status: 200, description: '`{data: {active: int, not_active: int}}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    public function counts(ListClientPackagesRequest $request, Center $center): JsonResponse
    {
        $active = (clone $this->baseQuery($request, $center))->where('status', ClientPackageStatus::Active)->count();
        $total = $this->baseQuery($request, $center)->count();

        return response()->json(['data' => ['active' => $active, 'not_active' => $total - $active]]);
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
