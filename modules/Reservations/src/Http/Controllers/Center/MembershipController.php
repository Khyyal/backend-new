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
use Modules\Reservations\Actions\BookVisitFromMembership;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Http\Requests\Center\ListClientMembershipsRequest;
use Modules\Reservations\Http\Resources\CenterClientMembershipDetailsResource;
use Modules\Reservations\Http\Resources\CenterClientMembershipResource;
use Modules\Reservations\Models\ClientMembership;

#[Group(name: 'Center / Client memberships', description: 'The memberships clients bought from the center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class MembershipController extends Controller
{
    public function __construct(private readonly BookVisitFromMembership $bookVisit) {}

    /**
     * List the center's client memberships.
     *
     * Paginated (25 per page), newest first. Unpaid (`pending`) memberships are never listed.
     *
     * **Query:**
     * - `active`: `1` keeps only active memberships.
     * - `not_active`: `1` keeps only the other statuses (expired, cancelled). With both flags set, all of them are returned.
     * - `q`: search in the client's name or phone number.
     * - `from` / `to`: inclusive range on the purchase date (`Y-m-d`).
     * - `service_id`: memberships of one membership service.
     */
    #[Response(status: 200, description: 'Paginated `CenterClientMembershipResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    public function index(ListClientMembershipsRequest $request, Center $center): AnonymousResourceCollection
    {
        $active = $request->boolean('active');
        $notActive = $request->boolean('not_active');

        $memberships = $this->baseQuery($request, $center)
            ->when($active && ! $notActive, fn (Builder $query) => $query->where('status', ClientMembershipStatus::Active))
            ->when($notActive && ! $active, fn (Builder $query) => $query->where('status', '!=', ClientMembershipStatus::Active))
            ->with(['client', 'service', 'membership', 'visitService'])
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return CenterClientMembershipResource::collection($memberships);
    }

    /**
     * Show a client membership.
     *
     * With its owner, its packages, the free visits left today and the visits booked from it
     * (`reservations`, by date). A pending (unpaid) membership is not found.
     */
    #[Response(status: 200, description: 'The `CenterClientMembershipDetailsResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 404, description: 'No such membership in this center.')]
    public function show(Request $request, Center $center, int $membership): CenterClientMembershipDetailsResource
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);

        $clientMembership = ClientMembership::query()
            ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
            ->where('status', '!=', ClientMembershipStatus::Pending)
            ->with([
                'client',
                'service',
                'membership',
                'visitService',
                'packages.service',
                'packages.priceOption',
                'reservations' => fn ($query) => $query->orderBy('date')->orderBy('start_time'),
            ])
            ->findOrFail($membership);

        return CenterClientMembershipDetailsResource::make($clientMembership)
            ->withVisitsUsedToday($this->bookVisit->usedVisits($clientMembership, CarbonImmutable::today()));
    }

    /**
     * Count the center's client memberships.
     *
     * Returns how many are active and how many are not (expired or cancelled). Unpaid
     * (`pending`) memberships are never counted. Takes the same `q`, `from`, `to` and `service_id` filters as the list.
     */
    #[Response(status: 200, description: '`{data: {active: int, not_active: int}}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    public function counts(ListClientMembershipsRequest $request, Center $center): JsonResponse
    {
        $active = (clone $this->baseQuery($request, $center))->where('status', ClientMembershipStatus::Active)->count();
        $total = $this->baseQuery($request, $center)->count();

        return response()->json(['data' => ['active' => $active, 'not_active' => $total - $active]]);
    }

    /**
     * @return Builder<ClientMembership>
     */
    private function baseQuery(ListClientMembershipsRequest $request, Center $center): Builder
    {
        return ClientMembership::query()
            ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
            ->where('status', '!=', ClientMembershipStatus::Pending)
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
