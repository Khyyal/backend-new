<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
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
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Admin / Purchases', 'Everything clients bought from centers. Purchases a center makes from the platform (subscriptions) are never listed. Requires `purchases.view_any`.')]
class PurchaseController extends Controller
{
    #[Endpoint('List client purchases', 'Newest first unless `sort` is given, paginated (25 by default). `payment_method` and `payment_status` are those of the latest payment.')]
    #[ResponseFromApiResource(PurchaseResource::class, Purchase::class, status: 200, description: 'Paginated purchases.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `purchases.view_any`.')]
    #[ApiResponse(['message' => 'The selected status is invalid.', 'errors' => ['status' => ['The selected status is invalid.']]], 422, 'Invalid filters.')]
    public function index(ListPurchasesRequest $request, AdminPurchaseQuery $query): AnonymousResourceCollection
    {
        $purchases = $query->build($request->validated())
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return PurchaseResource::collection($purchases);
    }

    #[Endpoint('Show a client purchase', 'With items (and what was booked), coupon, discounts and payments. Center-to-platform purchases return 404.')]
    #[UrlParam('purchase', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseFromApiResource(PurchaseDetailsResource::class, Purchase::class, status: 200, description: 'The purchase.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `purchases.view_any`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such client purchase.')]
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

    #[Endpoint('Export client purchases', 'Queues an `.xlsx` export (amounts in SAR) of the purchases matching the list filters. Download it from `GET /api/v1/admin/exports/{export}/download` once `completed`.')]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 202, description: 'The queued export.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `purchases.export`.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    #[ApiResponse(['message' => 'An export of this kind is already being generated. You will be notified when it is ready.'], 429, 'An export of this kind is already being generated.')]
    public function export(ExportPurchasesRequest $request, ExportService $exports): JsonResponse
    {
        $export = $exports->start($request->user(), ExportType::AdminPurchases, new AdminPurchasesExport($request->validated()));

        return ExportResource::make($export)->response()->setStatusCode(HttpStatus::HTTP_ACCEPTED);
    }
}
