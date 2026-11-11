<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Modules\Clients\Models\Client;
use Modules\Reservations\Actions\BookVisitFromMembership;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Http\Resources\ClientBookingResource;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Reservations', 'Book, list and cancel reservations, and buy and spend packages and memberships.')]
class BookingController extends Controller
{
    private const string TYPE_ALL = 'all';

    private const string TYPE_MEMBERSHIP = 'membership';

    private const string TYPE_PACKAGES = 'packages';

    private const string STATUS_ACTIVE = 'active';

    private const string STATUS_IN_ACTIVE = 'in_active';

    private const string KIND_RESERVATION = 'reservation';

    private const string KIND_PACKAGE = 'package';

    private const string KIND_MEMBERSHIP = 'membership';

    private const array RESERVATION_RELATIONS = ['service', 'trainer', 'priceOption', 'lines.priceOption'];

    private const array PACKAGE_RELATIONS = ['service', 'priceOption'];

    private const array MEMBERSHIP_RELATIONS = ['service', 'membership', 'visitService', 'packages.service', 'packages.priceOption'];

    public function __construct(private readonly BookVisitFromMembership $bookVisit) {}

    #[Endpoint('List my bookings', 'One feed of reservations, packages and memberships, newest first, 15 per page. Active: pending or confirmed reservations not yet over (sessions drawn from a package or membership are not listed alone), packages with sessions left and not expired, memberships not expired. Unpaid packages and memberships are in neither list.')]
    #[QueryParam('type', 'string', '`all` (default), `membership` or `packages`.', required: false, enum: ['all', 'membership', 'packages'], example: 'all')]
    #[QueryParam('status', 'string', '`active` (default) or `in_active` (history).', required: false, enum: ['active', 'in_active'], example: 'active')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ClientBookingResource::class, Reservation::class, status: 200, description: 'Paginated bookings.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The selected type is invalid.', 'errors' => ['type' => ['The selected type is invalid.']]], 422, 'Invalid `type` or `status`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in([self::TYPE_ALL, self::TYPE_MEMBERSHIP, self::TYPE_PACKAGES])],
            'status' => ['nullable', Rule::in([self::STATUS_ACTIVE, self::STATUS_IN_ACTIVE])],
        ]);

        $type = $filters['type'] ?? self::TYPE_ALL;
        $active = ($filters['status'] ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
        $client = $this->client($request);

        $branches = array_filter([
            $type === self::TYPE_ALL ? $this->reservations($client, $active) : null,
            $type !== self::TYPE_MEMBERSHIP ? $this->packages($client, $active) : null,
            $type !== self::TYPE_PACKAGES ? $this->memberships($client, $active) : null,
        ]);

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        $page = DB::query()
            ->fromSub($union, 'bookings')
            ->orderByDesc('created_at')
            ->orderBy('kind')
            ->orderByDesc('id')
            ->paginate(15);

        $models = $this->hydrate($page->getCollection()->groupBy('kind')->map->pluck('id'));
        $today = CarbonImmutable::today();

        return ClientBookingResource::collection($page->through(function (object $row) use ($models, $today): ClientBookingResource {
            $model = $models[$row->kind][$row->id];

            return ClientBookingResource::make([
                'kind' => $row->kind,
                'model' => $model,
                'visits_used_today' => $model instanceof ClientMembership ? $this->bookVisit->usedVisits($model, $today) : null,
            ]);
        }));
    }

    private function reservations(Client $client, bool $active): QueryBuilder
    {
        $isActive = fn (Builder $query) => $query
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereRaw('COALESCE(end_date, date) >= ?', [today()->toDateString()]);

        return $client->reservations()
            ->whereNull('client_package_id')
            ->whereNull('client_membership_id')
            ->when($active, fn (Builder $query) => $query->where($isActive), fn (Builder $query) => $query->whereNot($isActive))
            ->toBase()
            ->selectRaw("'".self::KIND_RESERVATION."' as kind, id, created_at");
    }

    private function packages(Client $client, bool $active): QueryBuilder
    {
        $isActive = fn (Builder $query) => $query
            ->where('status', ClientPackageStatus::Active)
            ->where('remaining_sessions', '>', 0)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        return $client->packages()
            ->when($active, fn (Builder $query) => $query->where($isActive), fn (Builder $query) => $query
                ->where('status', '!=', ClientPackageStatus::Pending)
                ->whereNot($isActive))
            ->toBase()
            ->selectRaw("'".self::KIND_PACKAGE."' as kind, id, created_at");
    }

    private function memberships(Client $client, bool $active): QueryBuilder
    {
        $isActive = fn (Builder $query) => $query
            ->where('status', ClientMembershipStatus::Active)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        return $client->memberships()
            ->when($active, fn (Builder $query) => $query->where($isActive), fn (Builder $query) => $query
                ->where('status', '!=', ClientMembershipStatus::Pending)
                ->whereNot($isActive))
            ->toBase()
            ->selectRaw("'".self::KIND_MEMBERSHIP."' as kind, id, created_at");
    }

    /**
     * @param  Collection<string, Collection<int, int>>  $idsByKind
     * @return array<string, Collection<int, Model>>
     */
    private function hydrate($idsByKind): array
    {
        return [
            self::KIND_RESERVATION => Reservation::query()->with(self::RESERVATION_RELATIONS)->findMany($idsByKind[self::KIND_RESERVATION] ?? [])->keyBy('id'),
            self::KIND_PACKAGE => ClientPackage::query()->with(self::PACKAGE_RELATIONS)->findMany($idsByKind[self::KIND_PACKAGE] ?? [])->keyBy('id'),
            self::KIND_MEMBERSHIP => ClientMembership::query()->with(self::MEMBERSHIP_RELATIONS)->findMany($idsByKind[self::KIND_MEMBERSHIP] ?? [])->keyBy('id'),
        ];
    }

    private function client(Request $request): Client
    {
        /** @var Client */
        return $request->user('client');
    }
}
