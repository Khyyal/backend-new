<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Exports\CenterPurchasesExport;
use Modules\Centers\Http\Requests\Center\ExportPurchasesRequest;
use Modules\Centers\Http\Requests\Center\ListPurchasesRequest;
use Modules\Centers\Http\Resources\Center\PurchaseDetailsResource;
use Modules\Centers\Http\Resources\Center\PurchaseResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterPurchaseQuery;
use Modules\Clients\Models\Client;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Services\ExportService;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Support\Models\SystemUser;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Center / Purchases', description: 'What clients bought from the center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class PurchaseController extends Controller
{
    public function __construct(private readonly CenterPurchaseQuery $purchases) {}

    /**
     * List the center's purchases.
     *
     * Paginated (25 per page), newest first. Non-primary users only see
     * purchases for the service types they may view, narrowed by trainer
     * (own trainer, other trainers, unassigned); amounts are omitted without
     * `purchases.view_prices`. `payment_method` and `payment_status` are those
     * of the latest payment, or `null` when there is none.
     *
     * **Query** (all optional):
     * - `status`        `pending`, `confirmed`, `cancelled` or `completed`.
     * - `q`             part of the client's full name or phone number.
     * - `service_id`    only purchases with a reservation or package of this service.
     * - `from`, `to`    `Y-m-d`, inclusive range on the purchase creation date.
     */
    #[Response(status: 200, description: 'Paginated `PurchaseResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s purchases.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    public function index(ListPurchasesRequest $request, Center $center): AnonymousResourceCollection
    {
        $purchases = $this->purchases->build($center, $request->user(), $request->validated())
            ->paginate(25)
            ->withQueryString();

        return PurchaseResource::collection($purchases);
    }

    /**
     * Show a purchase.
     *
     * With its line items and payments (amounts and payments omitted without
     * `purchases.view_prices`). Each item carries `details` of what was booked:
     * dates, times, service, trainer and price option for a reservation; service,
     * price option and sessions for a client package; `null` otherwise.
     * Purchases the user may not see return 404.
     */
    #[Response(status: 200, description: 'The `PurchaseDetailsResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s purchases.')]
    #[Response(status: 404, description: 'No such purchase in this center.')]
    public function show(Request $request, Center $center, int $purchase): PurchaseDetailsResource
    {
        $this->authorize($request, $center);

        $purchase = $this->purchases->visible($center, $request->user())->with(['buyer', 'items', 'payments', 'latestPayment', 'adjustments', 'fee', 'ledgerEntries'])->withCount('items')->findOrFail($purchase);

        $purchase->items
            ->whereIn('purchasable_type', [(new Reservation)->getMorphClass(), (new ClientPackage)->getMorphClass()])
            ->loadMorph('purchasable', [
                Reservation::class => ['service', 'trainer', 'priceOption'],
                ClientPackage::class => ['service', 'priceOption'],
            ]);

        return PurchaseDetailsResource::make($purchase);
    }

    private function authorize(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('viewPurchases', $center);
    }

    /**
     * Export the center's purchases.
     *
     * Queues an `.xlsx` export of the purchases the user may see, with the same filters as the list
     * (`status`, `q`, `service_id`, `from`/`to`). Amount columns (SAR) are left out without
     * `purchases.view_prices` (system users always get them). Center users and system users need
     * `purchases.export`. Download it from
     * the exports endpoints once ready.
     */
    #[Response(status: 202, description: 'The queued `ExportResource`; the requester is notified (`export_ready` / `export_failed`) when it finishes.')]
    #[Response(status: 403, description: 'No permission to export this center\'s purchases.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    #[Response(status: 429, description: 'An export of this kind is already being generated.')]
    public function export(ExportPurchasesRequest $request, Center $center, ExportService $exports): JsonResponse
    {
        $user = $request->user();

        $export = $exports->start(
            $user,
            $user instanceof SystemUser ? ExportType::AdminCenterPurchases : ExportType::CenterPurchases,
            new CenterPurchasesExport(
                $request->validated(),
                $center->id,
                $user->getMorphClass(),
                $user->getKey(),
                $user instanceof SystemUser || Gate::forUser($user)->allows('viewPurchasePrices', $center),
            ),
            $center->id,
        );

        return ExportResource::make($export)->response()->setStatusCode(HttpStatus::HTTP_ACCEPTED);
    }
}
