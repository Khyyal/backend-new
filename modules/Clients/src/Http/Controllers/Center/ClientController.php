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
     * Clients that belong to this center (registered from its page or created by it) or have at least one
     * confirmed or completed purchase in it. Paginated (25 per page), most recent buyer first, then clients
     * without purchases (newest first). Pending and cancelled purchases are ignored.
     */
    #[Response(status: 200, description: 'Paginated collection of `{id, full_name, phone_number, first_purchase_at, last_purchase_at, purchases_count}`. Purchase dates are `null` for clients without purchases.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);

        $purchaseStats = DB::table('purchases')
            ->where('buyer_type', (new Client)->getMorphClass())
            ->where('merchant_type', $center->getMorphClass())
            ->where('merchant_id', $center->getKey())
            ->whereIn('status', [PurchaseStatus::Confirmed->value, PurchaseStatus::Completed->value])
            ->whereNull('deleted_at')
            ->groupBy('buyer_id')
            ->select([
                'buyer_id',
                DB::raw('min(created_at) as first_purchase_at'),
                DB::raw('max(created_at) as last_purchase_at'),
                DB::raw('count(id) as purchases_count'),
            ]);

        $clients = DB::table('clients')
            ->leftJoinSub($purchaseStats, 'stats', 'stats.buyer_id', '=', 'clients.id')
            ->whereNull('clients.deleted_at')
            ->where(fn ($query) => $query
                ->whereNotNull('stats.buyer_id')
                ->orWhereExists(fn ($membership) => $membership
                    ->from('center_client')
                    ->whereColumn('center_client.client_id', 'clients.id')
                    ->where('center_client.center_id', $center->getKey())))
            ->select([
                'clients.id',
                'clients.first_name',
                'clients.last_name',
                'clients.phone_number',
                'stats.first_purchase_at',
                'stats.last_purchase_at',
                DB::raw('coalesce(stats.purchases_count, 0) as purchases_count'),
            ])
            ->orderByRaw('stats.last_purchase_at is null')
            ->orderByDesc('stats.last_purchase_at')
            ->orderByDesc('clients.id')
            ->paginate(25)
            ->withQueryString();

        return CenterClientResource::collection($clients);
    }
}
