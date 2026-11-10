<?php

namespace Modules\Settlements\Http\Controllers\Admin;

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
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Modules\Purchase\Models\Purchase;
use Modules\Settlements\Exports\CenterLedgerExport;
use Modules\Settlements\Http\Requests\Admin\ExportCenterLedgerRequest;
use Modules\Settlements\Http\Requests\Admin\ListCenterLedgerRequest;
use Modules\Settlements\Http\Resources\CenterLedgerEntryResource;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Services\CenterBalance;
use Modules\Settlements\Services\CenterLedgerQuery;
use Modules\Settlements\Services\PurchaseFinancials;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Admin / Payouts', 'Center ledgers and purchase money splits. Requires `payouts.manage` (`payouts.export` to export).')]
class CenterLedgerController extends Controller
{
    #[Endpoint('Show a center ledger', 'Every movement of the center balance, newest first unless `sort` is given, paginated, with the balance summary in `meta.balance`.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[ResponseFromApiResource(CenterLedgerEntryResource::class, CenterLedgerEntry::class, status: 200, description: 'Paginated entries; `meta.balance` has the summary.', paginate: 25)]
    #[ResponseField('meta.balance.available', 'integer', 'Goes into the next weekly payout, in halalas (negative carries forward).', example: 812500)]
    #[ResponseField('meta.balance.on_hold', 'integer', 'Still within the hold period, in halalas.', example: 120000)]
    #[ResponseField('meta.balance.in_pending_payouts', 'integer', 'In pending payouts awaiting transfer, in halalas.', example: 0)]
    #[ResponseField('meta.balance.paid_out', 'integer', 'Already transferred, in halalas.', example: 4300000)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(['message' => 'The selected type is invalid.', 'errors' => ['type' => ['The selected type is invalid.']]], 422, 'Invalid filters.')]
    public function index(ListCenterLedgerRequest $request, Center $center, CenterBalance $balance, CenterLedgerQuery $query): AnonymousResourceCollection
    {
        $entries = $query->build($center, $request->validated())
            ->paginate(min(100, max(1, $request->integer('per_page', 25))))
            ->withQueryString();

        return CenterLedgerEntryResource::collection($entries)
            ->additional(['meta' => ['balance' => $balance->for($center)]]);
    }

    #[Endpoint('Show how a purchase\'s money splits', '`gross_paid`, `refunded` and `provider_fee` come from its payments; `platform_fee`, `platform_subsidy` and `center_liability` from the center ledger; `platform_net_revenue` is what the platform keeps. Halalas.')]
    #[UrlParam('purchase', 'integer', 'Purchase ID.', example: 5521)]
    #[ApiResponse(['data' => ['gross_paid' => 29325, 'refunded' => 0, 'provider_fee' => 645, 'platform_fee' => 1466, 'platform_subsidy' => 0, 'center_liability' => 27859, 'platform_net_revenue' => 821]], 200, 'The split.')]
    #[ResponseField('data.gross_paid', 'integer', 'Paid by the client.', example: 29325)]
    #[ResponseField('data.refunded', 'integer', 'Refunded.', example: 0)]
    #[ResponseField('data.provider_fee', 'integer', 'Payment provider fee.', example: 645)]
    #[ResponseField('data.platform_fee', 'integer', 'Fee charged to the center.', example: 1466)]
    #[ResponseField('data.platform_subsidy', 'integer', 'Platform-funded discount credited to the center.', example: 0)]
    #[ResponseField('data.center_liability', 'integer', 'What the platform owes the center (negative for cash sales).', example: 27859)]
    #[ResponseField('data.platform_net_revenue', 'integer', 'What the platform keeps.', example: 821)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such purchase.')]
    public function financials(Request $request, Purchase $purchase, PurchaseFinancials $financials): array
    {
        Gate::forUser($request->user())->authorize('viewAny', CenterPayout::class);

        return ['data' => $financials->for($purchase)];
    }

    #[Endpoint('Export a center ledger', 'Queues an `.xlsx` export (SAR, negative when the center owes the platform) with the ledger filters. Download it from `GET /api/v1/admin/exports/{export}/download`.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 202, description: 'The queued export.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.export`.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    #[ApiResponse(['message' => 'An export of this kind is already being generated. You will be notified when it is ready.'], 429, 'An export of this kind is already being generated.')]
    public function export(ExportCenterLedgerRequest $request, Center $center, ExportService $exports): JsonResponse
    {
        $export = $exports->start(
            $request->user(),
            ExportType::AdminCenterLedger,
            new CenterLedgerExport($request->validated(), $center->id),
            $center->id,
        );

        return ExportResource::make($export)->response()->setStatusCode(HttpStatus::HTTP_ACCEPTED);
    }
}
