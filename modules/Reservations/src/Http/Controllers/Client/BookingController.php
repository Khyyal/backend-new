<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Clients\Models\Client;
use Modules\Reservations\Actions\BookVisitFromMembership;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Http\Resources\ClientBookingResource;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;

#[Group(name: 'Client / Reservations', description: 'Book, list and cancel reservations, and buy and spend service packages. Requires a client Bearer token.')]
class BookingController extends Controller
{
    private const string TYPE_ALL = 'all';

    private const string TYPE_MEMBERSHIP = 'membership';

    private const string TYPE_PACKAGES = 'packages';

    private const string KIND_RESERVATION = 'reservation';

    private const string KIND_PACKAGE = 'package';

    private const string KIND_MEMBERSHIP = 'membership';

    private const array RESERVATION_RELATIONS = ['service', 'trainer', 'lines.priceOption'];

    private const array PACKAGE_RELATIONS = ['service', 'priceOption'];

    private const array MEMBERSHIP_RELATIONS = ['service', 'membership', 'visitService', 'packages.service', 'packages.priceOption'];

    public function __construct(private readonly BookVisitFromMembership $bookVisit) {}

    /**
     * List my active bookings.
     *
     * One paginated feed (15 per page), newest first, mixing stand-alone reservations, packages and
     * memberships. Each item is `{kind, data}` where `kind` is `reservation`, `package` or `membership`
     * and `data` is the matching `ReservationResource`, `ClientPackageResource` or `ClientMembershipResource`.
     *
     * Active means: a pending or confirmed reservation not yet over (sessions drawn from a package or
     * membership are not listed on their own); a package with sessions left that has not expired; a
     * membership that has not expired.
     *
     * **Query:** `type` (`all` by default, `membership` for memberships only, `packages` for packages only).
     */
    #[Response(status: 200, description: 'Paginated `ClientBookingResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 422, description: 'Invalid `type`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in([self::TYPE_ALL, self::TYPE_MEMBERSHIP, self::TYPE_PACKAGES])],
        ]);

        $type = $filters['type'] ?? self::TYPE_ALL;
        $client = $this->client($request);

        $branches = array_filter([
            $type === self::TYPE_ALL ? $this->reservations($client) : null,
            $type !== self::TYPE_MEMBERSHIP ? $this->packages($client) : null,
            $type !== self::TYPE_PACKAGES ? $this->memberships($client) : null,
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

    private function reservations(Client $client): QueryBuilder
    {
        return $client->reservations()
            ->whereNull('client_package_id')
            ->whereNull('client_membership_id')
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereRaw('COALESCE(end_date, date) >= ?', [today()->toDateString()])
            ->toBase()
            ->selectRaw("'".self::KIND_RESERVATION."' as kind, id, created_at");
    }

    private function packages(Client $client): QueryBuilder
    {
        return $client->packages()
            ->where('status', ClientPackageStatus::Active)
            ->where('remaining_sessions', '>', 0)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->toBase()
            ->selectRaw("'".self::KIND_PACKAGE."' as kind, id, created_at");
    }

    private function memberships(Client $client): QueryBuilder
    {
        return $client->memberships()
            ->where('status', ClientMembershipStatus::Active)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
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
