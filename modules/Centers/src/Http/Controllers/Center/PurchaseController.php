<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Exports\CenterPurchasesExport;
use Modules\Centers\Http\Requests\Center\ExportPurchasesRequest;
use Modules\Centers\Http\Requests\Center\ListPurchasesRequest;
use Modules\Centers\Http\Resources\Center\PurchaseDetailsResource;
use Modules\Centers\Http\Resources\Center\PurchaseResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterPurchaseQuery;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Actions\RefundPurchase;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Models\SystemUser;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Purchases', 'What clients bought from a center. Served on `centers/{center}/…` (center-scoped token) and `admin/centers/{center}/…` (system user). Non-primary center users only see purchases of the service types they may view (`purchases.{type}.view`), narrowed by trainer; amounts need `purchases.view_prices`. System users need `centers.manage_reservations` (and `purchases.export` to export) and always see amounts.')]
class PurchaseController extends Controller
{
    public function __construct(private readonly CenterPurchaseQuery $purchases) {}

    #[Endpoint('List the center\'s purchases', 'Newest first, 25 per page. `payment_method` and `payment_status` are those of the latest payment.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(PurchaseResource::class, Purchase::class, status: 200, description: 'Paginated purchases.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s purchases. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The selected status is invalid.', 'errors' => ['status' => ['The selected status is invalid.']]], 422, 'Invalid filters.')]
    public function index(ListPurchasesRequest $request, Center $center): AnonymousResourceCollection
    {
        $purchases = $this->purchases->build($center, $request->user(), $request->validated())
            ->paginate(25)
            ->withQueryString();

        return PurchaseResource::collection($purchases);
    }

    #[Endpoint('Show a purchase', 'With line items, discounts and payments (amounts and payments only with `purchases.view_prices`). Purchases you may not see return 404.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('purchase', 'integer', 'Purchase ID (must belong to the center and be visible to you).', example: 5521)]
    #[ResponseFromApiResource(PurchaseDetailsResource::class, Purchase::class, status: 200, description: 'The purchase.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s purchases. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such purchase in this center.')]
    public function show(Request $request, Center $center, int $purchase): PurchaseDetailsResource
    {
        $this->authorize($request, $center);

        return $this->details($this->purchases->visible($center, $request->user())->whereKey($purchase));
    }

    #[Endpoint('Show a purchase by code', 'Looks the purchase up by its public 6-character `code` (case-insensitive). Same response and visibility rules as showing it by ID.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('code', 'string', 'Purchase code (must belong to the center and be visible to you).', example: 'K7QX2M')]
    #[ResponseFromApiResource(PurchaseDetailsResource::class, Purchase::class, status: 200, description: 'The purchase.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s purchases. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No purchase with this code in this center.')]
    public function showByCode(Request $request, Center $center, string $code): PurchaseDetailsResource
    {
        $this->authorize($request, $center);

        return $this->details($this->purchases->visible($center, $request->user())->whereCode($code));
    }

    #[Endpoint('Refund a purchase', 'Gives back everything still refundable on a confirmed purchase and cancels it with what it sold: open reservations, packages and memberships (with their upcoming sessions). Online payments are refunded through their gateway; a manual payment is only recorded as refunded, hand the money back yourself. When the gateway refuses, nothing changes. Needs `reservations.edit`, and the purchase must be visible to you.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('purchase', 'integer', 'Purchase ID (must belong to the center and be visible to you).', example: 5521)]
    #[Header('Idempotency-Key', '7c9e6679-7425-40de-944b-e07fc1f90ae7')]
    #[ResponseFromApiResource(PurchaseDetailsResource::class, Purchase::class, status: 200, description: 'The cancelled purchase with its refunded payments.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `reservations.edit` for this center (system users: `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such purchase in this center.')]
    #[ApiResponse(['message' => 'This purchase has no payment left to refund.', 'reason' => 'nothing_to_refund', 'errors' => ['reservation' => ['This purchase has no payment left to refund.']]], 422, 'The purchase cannot be refunded (`reason`: purchase_not_refundable, nothing_to_refund, purchase_refund_failed).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_KEY_REQUIRED, 400, 'Missing `Idempotency-Key` header. Send a unique UUID per operation and reuse it unchanged on retries (a replay returns the stored response with `Idempotent-Replayed: true`).')]
    #[ApiResponse(ApiExamples::IDEMPOTENCY_IN_PROGRESS, 409, 'A request with this `Idempotency-Key` is still running; retry after `Retry-After`.')]
    public function refund(Request $request, Center $center, int $purchase, RefundPurchase $refundPurchase): PurchaseDetailsResource
    {
        Gate::forUser($request->user())->authorize('editReservations', $center);

        $visible = $this->purchases->visible($center, $request->user())->whereKey($purchase);

        $refundPurchase->execute((clone $visible)->firstOrFail(), $request->user());

        return $this->details($visible);
    }

    /**
     * The single purchase the query matches, with everything the details resource shows.
     *
     * @param  Builder<Purchase>  $query
     */
    private function details(Builder $query): PurchaseDetailsResource
    {
        $purchase = $query->with(['buyer', 'items', 'payments', 'latestPayment', 'adjustments', 'fee', 'ledgerEntries'])->withCount('items')->firstOrFail();

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

    #[Endpoint('Export the center\'s purchases', 'Queues an `.xlsx` export of the purchases you may see, with the list filters. Amount columns need `purchases.view_prices` (system users always get them). Needs `purchases.export`.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 202, description: 'The queued export; the requester gets an `export_ready` or `export_failed` notification when it finishes.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `purchases.export` for this center.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    #[ApiResponse(['message' => 'An export of this kind is already being generated. You will be notified when it is ready.'], 429, 'An export of this kind is already being generated.')]
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
