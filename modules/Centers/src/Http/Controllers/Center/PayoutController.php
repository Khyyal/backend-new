<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Requests\Center\UpdatePayoutAccountRequest;
use Modules\Centers\Models\Center;
use Modules\Purchase\Http\Resources\CenterPayoutResource;
use Modules\Purchase\Models\CenterPayout;
use Modules\Purchase\Services\CenterBalance;

#[Group(name: 'Center / Payouts', description: 'What the platform owes the center and the weekly transfers of it. Online payments become payable after a hold period; the platform fee (also on cash sales) is netted against them. Amounts are halalas. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class PayoutController extends Controller
{
    /**
     * List the center's payouts.
     *
     * Paginated (25 per page), newest first. Requires `payouts.view`.
     */
    #[Response(status: 200, description: 'Paginated `CenterPayoutResource` collection.')]
    #[Response(status: 403, description: 'No access to this center\'s payouts.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewPayouts', $center);

        return CenterPayoutResource::collection(
            $center->payouts()->latest('id')->paginate(25)
        );
    }

    /**
     * Show a payout with the ledger entries it pays.
     */
    #[Response(status: 200, description: 'The `CenterPayoutResource` with `entries`.')]
    #[Response(status: 403, description: 'No access to this center\'s payouts.')]
    #[Response(status: 404, description: 'No such payout in this center.')]
    public function show(Request $request, Center $center, int $payout): CenterPayoutResource
    {
        Gate::forUser($request->user())->authorize('viewPayouts', $center);

        /** @var CenterPayout $model */
        $model = $center->payouts()->with('entries')->findOrFail($payout);

        return CenterPayoutResource::make($model);
    }

    /**
     * Show the center's balance and payout account.
     *
     * `available` goes into the next weekly payout (negative carries forward),
     * `on_hold` is still within the hold period, `in_pending_payouts` awaits
     * transfer and `paid_out` has been transferred. `account` is `null` until
     * the primary user sets it; payouts are skipped without one.
     */
    #[Response(status: 200, description: '`{data: {available, on_hold, in_pending_payouts, paid_out, account}}`')]
    #[Response(status: 403, description: 'No access to this center\'s payouts.')]
    public function balance(Request $request, Center $center, CenterBalance $balance): array
    {
        Gate::forUser($request->user())->authorize('viewPayouts', $center);

        return ['data' => [
            ...$balance->for($center),
            'account' => $center->payout_iban === null ? null : [
                'iban' => $center->payout_iban,
                'account_name' => $center->payout_account_name,
                'bank_name' => $center->payout_bank_name,
            ],
        ]];
    }

    /**
     * Set the bank account payouts are sent to.
     *
     * Primary user only. Payouts already created keep the account they were
     * created with.
     */
    #[Response(status: 200, description: '`{data: {iban, account_name, bank_name}}`')]
    #[Response(status: 403, description: 'Not the center\'s primary user.')]
    #[Response(status: 422, description: 'Invalid IBAN (`SA` + 22 digits) or missing fields.')]
    public function updateAccount(UpdatePayoutAccountRequest $request, Center $center): array
    {
        Gate::forUser($request->user())->authorize('update', $center);

        $center->update([
            'payout_iban' => $request->validated('iban'),
            'payout_account_name' => $request->validated('account_name'),
            'payout_bank_name' => $request->validated('bank_name'),
        ]);

        return ['data' => [
            'iban' => $center->payout_iban,
            'account_name' => $center->payout_account_name,
            'bank_name' => $center->payout_bank_name,
        ]];
    }
}
