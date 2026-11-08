<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Services\ExportService;
use Modules\Purchase\Exports\AdminPurchasesExport;
use Modules\Purchase\Http\Requests\Admin\ExportPurchasesRequest;
use Modules\Purchase\Http\Requests\Admin\ListPurchasesRequest;
use Modules\Purchase\Http\Resources\Admin\PurchaseDetailsResource;
use Modules\Purchase\Http\Resources\Admin\PurchaseResource;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\AdminPurchaseQuery;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

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
    public function index(ListPurchasesRequest $request, AdminPurchaseQuery $query): AnonymousResourceCollection
    {
        $purchases = $query->build($request->validated())
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

    /**
     * Export client purchases.
     *
     * Queues an `.xlsx` export of the purchases matching the same filters as the list (`center_id`,
     * `client_id`, `q`, `status`, `source`, `channel`, `payment_method`, `payment_status`, `service_id`,
     * `from`/`to`). Amounts are in SAR. Download it from `GET /admin/exports/{export}/download` once ready.
     */
    #[Response(status: 202, description: 'The queued `ExportResource`; the requester is notified (`export_ready` / `export_failed`) when it finishes.')]
    #[Response(status: 403, description: 'Missing the `purchases.export` permission.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    #[Response(status: 429, description: 'An export of this kind is already being generated.')]
    public function export(ExportPurchasesRequest $request, ExportService $exports): JsonResponse
    {
        $export = $exports->start($request->user(), ExportType::AdminPurchases, new AdminPurchasesExport($request->validated()));

        return ExportResource::make($export)->response()->setStatusCode(HttpStatus::HTTP_ACCEPTED);
    }
}
