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
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Reservations\Actions\BookVisitFromMembership;
use Modules\Reservations\Actions\CheckInClientMembership;
use Modules\Reservations\Actions\ExtendClientMembership;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Http\Requests\Center\CheckInClientMembershipRequest;
use Modules\Reservations\Http\Requests\Center\ExtendClientMembershipRequest;
use Modules\Reservations\Http\Requests\Center\ListClientMembershipLogsRequest;
use Modules\Reservations\Http\Requests\Center\ListClientMembershipsRequest;
use Modules\Reservations\Http\Resources\CenterClientMembershipDetailsResource;
use Modules\Reservations\Http\Resources\CenterClientMembershipResource;
use Modules\Reservations\Http\Resources\ClientMembershipLogResource;
use Modules\Reservations\Http\Resources\PurchaseSummaryResource;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientMembershipLog;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client memberships', 'Memberships clients bought from a center. Unpaid (`pending`) memberships are never listed or counted. Served on `centers/{center}/…` (center-scoped token) and `admin/centers/{center}/…` (system user).')]
class MembershipController extends Controller
{
    public function __construct(
        private readonly BookVisitFromMembership $bookVisit,
        private readonly ExtendClientMembership $extendClientMembership,
        private readonly CheckInClientMembership $checkInClientMembership,
    ) {}

    #[Endpoint('List client memberships', 'Newest first, 25 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterClientMembershipResource::class, ClientMembership::class, status: 200, description: 'Paginated memberships.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    public function index(ListClientMembershipsRequest $request, Center $center): AnonymousResourceCollection
    {
        $active = $request->boolean('active');
        $notActive = $request->boolean('not_active');

        $memberships = $this->baseQuery($request, $center)
            ->when($active && ! $notActive, fn (Builder $query) => $query->where('status', ClientMembershipStatus::Active))
            ->when($notActive && ! $active, fn (Builder $query) => $query->where('status', '!=', ClientMembershipStatus::Active))
            ->with(['client', 'service', 'membership', 'visitService', ...PurchaseSummaryResource::RELATIONS])
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return CenterClientMembershipResource::collection($memberships);
    }

    #[Endpoint('Show a client membership', 'With its owner, packages, free visits left today and the visits booked from it.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('membership', 'integer', 'Client membership ID.', example: 9)]
    #[ResponseFromApiResource(CenterClientMembershipDetailsResource::class, ClientMembership::class, status: 200, description: 'The membership.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid membership in this center.')]
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
                ...PurchaseSummaryResource::RELATIONS,
                'packages.service',
                'packages.priceOption',
                'reservations' => fn ($query) => $query->orderBy('date')->orderBy('start_time'),
            ])
            ->findOrFail($membership);

        return CenterClientMembershipDetailsResource::make($clientMembership)
            ->withVisitsUsedToday($this->bookVisit->usedVisits($clientMembership, CarbonImmutable::today()));
    }

    #[Endpoint('Extend a client membership', 'Sets a later `expires_at`. An expired membership becomes `active` again; the packages that came with it keep their own validity. Pending and cancelled memberships cannot be changed.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('membership', 'integer', 'Client membership ID.', example: 9)]
    #[ResponseFromApiResource(CenterClientMembershipDetailsResource::class, ClientMembership::class, status: 200, description: 'The updated membership.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid membership in this center.')]
    #[ApiResponse(['message' => 'The new expiry must be later than the current one.', 'reason' => 'invalid_expiry', 'errors' => ['reservation' => ['The new expiry must be later than the current one.']]], 422, 'Validation failed, or the change is not allowed (`reason`: invalid_expiry, membership_not_adjustable).')]
    public function update(ExtendClientMembershipRequest $request, Center $center, int $membership): CenterClientMembershipDetailsResource
    {
        $clientMembership = $this->extendClientMembership->execute(
            ClientMembership::query()
                ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
                ->where('status', '!=', ClientMembershipStatus::Pending)
                ->findOrFail($membership),
            CarbonImmutable::parse($request->validated('expires_at')),
        );

        $clientMembership->load([
            'client', 'service', 'membership', 'visitService', ...PurchaseSummaryResource::RELATIONS,
            'packages.service', 'packages.priceOption',
            'reservations' => fn ($query) => $query->orderBy('date')->orderBy('start_time'),
        ]);

        return CenterClientMembershipDetailsResource::make($clientMembership)
            ->withVisitsUsedToday($this->bookVisit->usedVisits($clientMembership, CarbonImmutable::today()));
    }

    #[Endpoint('Check a client membership in', 'Adds a `check_in` entry to the membership\'s log on every call; it does not touch reservations. The membership must be active and not expired.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('membership', 'integer', 'Client membership ID.', example: 9)]
    #[ResponseFromApiResource(ClientMembershipLogResource::class, ClientMembershipLog::class, status: 201, description: 'The new log entry.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid membership in this center.')]
    #[ApiResponse(['message' => 'This membership is not active or has expired.', 'reason' => 'membership_not_usable', 'errors' => ['reservation' => ['This membership is not active or has expired.']]], 422, 'Validation failed, or the membership is not usable (`reason: membership_not_usable`).')]
    public function checkIn(CheckInClientMembershipRequest $request, Center $center, int $membership): JsonResponse
    {
        $log = $this->checkInClientMembership->execute(
            $this->paidMemberships($center)->findOrFail($membership),
            $request->user(),
            $request->validated('notes'),
        );

        return ClientMembershipLogResource::make($log->load(['actor']))
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint('List a client membership\'s log', 'Guest invites and check-ins, newest first, 25 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('membership', 'integer', 'Client membership ID.', example: 9)]
    #[ResponseFromApiResource(ClientMembershipLogResource::class, ClientMembershipLog::class, status: 200, description: 'Paginated log entries.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such paid membership in this center.')]
    public function logs(ListClientMembershipLogsRequest $request, Center $center, int $membership): AnonymousResourceCollection
    {
        $logs = $this->paidMemberships($center)->findOrFail($membership)
            ->logs()
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->with(['guest', 'reservation', 'actor'])
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return ClientMembershipLogResource::collection($logs);
    }

    #[Endpoint('Count client memberships', 'Active vs. not active (expired, cancelled), with the list filters `q`, `from`, `to`, `service_id`.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => ['active' => 12, 'not_active' => 30]], 200, 'Counts.')]
    #[ResponseField('data.active', 'integer', 'Active ones.', example: 12)]
    #[ResponseField('data.not_active', 'integer', 'All other paid statuses.', example: 30)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s reservations (center users: `reservations.manage`; system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The from field must match the format Y-m-d.', 'errors' => ['from' => ['The from field must match the format Y-m-d.']]], 422, 'Invalid filters.')]
    public function counts(ListClientMembershipsRequest $request, Center $center): JsonResponse
    {
        $active = (clone $this->baseQuery($request, $center))->where('status', ClientMembershipStatus::Active)->count();
        $total = $this->baseQuery($request, $center)->count();

        return response()->json(['data' => ['active' => $active, 'not_active' => $total - $active]]);
    }

    /**
     * @return Builder<ClientMembership>
     */
    private function paidMemberships(Center $center): Builder
    {
        return ClientMembership::query()
            ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
            ->where('status', '!=', ClientMembershipStatus::Pending);
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
