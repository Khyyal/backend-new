<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Http\Requests\Admin\ListPurchasesRequest;
use Modules\Purchase\Http\Resources\Admin\PurchaseDetailsResource;
use Modules\Purchase\Http\Resources\Admin\PurchaseResource;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;

#[Group(name: 'Admin / Purchases', description: 'Platform-wide view of what clients bought from centers. Requires a system user Bearer token with the `purchases.view_any` permission.')]
class PurchaseController extends Controller
{
    /**
     * List all client purchases.
     *
     * Only purchases a client made from a center; purchases a center makes from the platform (e.g. subscriptions)
     * are never listed. Paginated (`per_page`, default 25, max 100), newest first. `payment_method` and
     * `payment_status` are those of the latest payment, or `null` when there is none.
     *
     * **Query** (all optional, combinable): `center_id`, `client_id`, `q` (client full name or phone number),
     * `status`, `source`, `channel`, `payment_method`, `payment_status`, `service_id` (purchases with a reservation
     * or package of this service) and `from`/`to` (`Y-m-d`, inclusive, on the creation date).
     */
    #[Response(status: 200, description: 'Paginated `PurchaseResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid system user Bearer token.')]
    #[Response(status: 403, description: 'Missing the `purchases.view_any` permission.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    public function index(ListPurchasesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $purchases = Purchase::query()
            ->where('buyer_type', (new Client)->getMorphClass())
            ->where('merchant_type', (new Center)->getMorphClass())
            ->when($filters['center_id'] ?? null, fn (Builder $query, int $id) => $query->where('merchant_id', $id))
            ->when($filters['client_id'] ?? null, fn (Builder $query, int $id) => $query->where('buyer_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['source'] ?? null, fn (Builder $query, string $value) => $query->where('source', $value))
            ->when($filters['channel'] ?? null, fn (Builder $query, string $value) => $query->where('channel', $value))
            ->when(filled($filters['q'] ?? null), fn (Builder $query) => $query->whereHasMorph(
                'buyer',
                [Client::class],
                fn (Builder $client) => $client->search($filters['q']),
            ))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $value) => $query->whereHas('latestPayment', fn (Builder $payment) => $payment->where('method', $value)))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $value) => $query->whereHas('latestPayment', fn (Builder $payment) => $payment->where('status', $value)))
            ->when($filters['service_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('items', fn (Builder $items) => $items->whereHasMorph(
                'purchasable',
                [Reservation::class, ClientPackage::class],
                fn (Builder $purchasable) => $purchasable->where('service_id', $id),
            )))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->with(['buyer', 'merchant', 'latestPayment', 'fee'])
            ->withCount('items')
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return PurchaseResource::collection($purchases);
    }

    /**
     * Show a client purchase.
     *
     * The list fields plus `items` (each with `details` of what was booked: dates, times, service, trainer and
     * price option for a reservation; service, price option and sessions for a client package; `null` otherwise),
     * `coupon_code`, `adjustments` and `payments`. Center-to-platform purchases are not found (404).
     */
    #[Response(status: 200, description: 'The `PurchaseDetailsResource`.')]
    #[Response(status: 401, description: 'Missing or invalid system user Bearer token.')]
    #[Response(status: 403, description: 'Missing the `purchases.view_any` permission.')]
    #[Response(status: 404, description: 'No such client purchase.')]
    public function show(Request $request, int $purchase): PurchaseDetailsResource
    {
        Gate::forUser($request->user())->authorize('viewAny', Purchase::class);

        $purchase = Purchase::query()
            ->where('buyer_type', (new Client)->getMorphClass())
            ->where('merchant_type', (new Center)->getMorphClass())
            ->with(['buyer', 'merchant', 'items', 'payments', 'latestPayment', 'adjustments', 'fee'])
            ->withCount('items')
            ->findOrFail($purchase);

        $purchase->items
            ->whereIn('purchasable_type', [(new Reservation)->getMorphClass(), (new ClientPackage)->getMorphClass()])
            ->loadMorph('purchasable', [
                Reservation::class => ['service', 'trainer', 'priceOption'],
                ClientPackage::class => ['service', 'priceOption'],
            ]);

        return PurchaseDetailsResource::make($purchase);
    }
}
