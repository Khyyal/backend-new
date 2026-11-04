<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Purchase\Http\Resources\CenterLedgerEntryResource;
use Modules\Purchase\Models\CenterLedgerEntry;
use Modules\Purchase\Models\CenterPayout;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\CenterBalance;
use Modules\Purchase\Services\PurchaseFinancials;

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
     */
    #[ApiResponse(status: 200, description: 'Paginated `CenterLedgerEntryResource` collection.')]
    public function index(Request $request, Center $center, CenterBalance $balance): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', CenterPayout::class);

        $entries = CenterLedgerEntry::query()
            ->where('center_id', $center->id)
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 25))));

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
}
