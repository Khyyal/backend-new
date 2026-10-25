<?php

namespace Modules\Clients\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Clients\Http\Resources\CenterClientResource;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PurchaseStatus;

#[Group(name: 'Center / Clients', description: 'The clients who bought from the center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ClientController extends Controller
{
    /**
     * List the center's clients.
     *
     * Clients with at least one confirmed or completed purchase in this center. Paginated (25 per page),
     * most recent buyer first. Pending and cancelled purchases are ignored.
     */
    #[Response(status: 200, description: 'Paginated collection of `{id, full_name, phone_number, first_purchase_at, last_purchase_at, purchases_count}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);

        $clients = DB::table('purchases')
            ->join('clients', 'clients.id', '=', 'purchases.buyer_id')
            ->where('purchases.buyer_type', (new Client)->getMorphClass())
            ->where('purchases.merchant_type', $center->getMorphClass())
            ->where('purchases.merchant_id', $center->getKey())
            ->whereIn('purchases.status', [PurchaseStatus::Confirmed->value, PurchaseStatus::Completed->value])
            ->whereNull('purchases.deleted_at')
            ->whereNull('clients.deleted_at')
            ->groupBy('clients.id', 'clients.first_name', 'clients.last_name', 'clients.phone_number')
            ->select([
                'clients.id',
                'clients.first_name',
                'clients.last_name',
                'clients.phone_number',
                DB::raw('min(purchases.created_at) as first_purchase_at'),
                DB::raw('max(purchases.created_at) as last_purchase_at'),
                DB::raw('count(purchases.id) as purchases_count'),
            ])
            ->orderByDesc('last_purchase_at')
            ->orderBy('clients.id')
            ->paginate(25)
            ->withQueryString();

        return CenterClientResource::collection($clients);
    }
}
