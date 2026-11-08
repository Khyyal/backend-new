<?php

namespace Modules\Settlements\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Services\ExportService;
use Modules\Purchase\Models\Purchase;
use Modules\Settlements\Exports\CenterLedgerExport;
use Modules\Settlements\Http\Requests\Admin\ExportCenterLedgerRequest;
use Modules\Settlements\Http\Requests\Admin\ListCenterLedgerRequest;
use Modules\Settlements\Http\Resources\CenterLedgerEntryResource;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Services\CenterBalance;
use Modules\Settlements\Services\CenterLedgerQuery;
use Modules\Settlements\Services\PurchaseFinancials;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Admin / Payouts')]
class CenterLedgerController extends Controller
{
    /**
     * A center's ledger.
     *
     * Every movement of the center's balance, newest first, with the balance
     * summary in `meta.balance` (`available`, `on_hold`, `in_pending_payouts`,
     * `paid_out`). Entry types: `payment`, `refund`, `platform_fee`,
     * `fee_reversal`, `platform_subsidy`, `subsidy_reversal`.
     *
     * **Query** (all optional): `type`, `paid_out` (boolean), `from`/`to`
     * (`Y-m-d`, inclusive, on the entry date), `per_page` (default 25, max 100).
     */
    #[ApiResponse(status: 200, description: 'Paginated `CenterLedgerEntryResource` collection.')]
    public function index(ListCenterLedgerRequest $request, Center $center, CenterBalance $balance, CenterLedgerQuery $query): AnonymousResourceCollection
    {
        $entries = $query->build($center, $request->validated())
            ->paginate(min(100, max(1, $request->integer('per_page', 25))))
            ->withQueryString();

        return CenterLedgerEntryResource::collection($entries)
            ->additional(['meta' => ['balance' => $balance->for($center)]]);
    }

    /**
     * How a purchase's money splits.
     *
     * `gross_paid`, `refunded` and `provider_fee` come from its payments;
     * `platform_fee`, `platform_subsidy` and `center_liability` (what the
     * platform owes the center, negative for cash sales) from the center
     * ledger; `platform_net_revenue` is what the platform keeps.
     */
    #[ApiResponse(status: 200, description: '`{data: {gross_paid, refunded, provider_fee, platform_fee, platform_subsidy, center_liability, platform_net_revenue}}`')]
    public function financials(Request $request, Purchase $purchase, PurchaseFinancials $financials): array
    {
        Gate::forUser($request->user())->authorize('viewAny', CenterPayout::class);

        return ['data' => $financials->for($purchase)];
    }

    /**
     * Export a center's ledger.
     *
     * Queues an `.xlsx` export of the center's ledger entries matching the same filters as the ledger
     * (`type`, `paid_out`, `from`/`to`). Amounts are in SAR, negative when the center owes the platform.
     * Download it from `GET /admin/exports/{export}/download` once ready.
     */
    #[ApiResponse(status: 202, description: 'The queued `ExportResource`; the requester is notified (`export_ready` / `export_failed`) when it finishes.')]
    #[ApiResponse(status: 403, description: 'Missing the `payouts.export` permission.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    #[ApiResponse(status: 429, description: 'An export of this kind is already being generated.')]
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
