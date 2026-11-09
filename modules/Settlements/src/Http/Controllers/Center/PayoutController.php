<?php

namespace Modules\Settlements\Http\Controllers\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Settlements\Http\Requests\Center\UpdatePayoutAccountRequest;
use Modules\Settlements\Http\Resources\CenterPayoutResource;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Services\CenterBalance;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Payouts', 'What the platform owes the center and the weekly transfers of it. Online payments become payable after a hold period; the platform fee (also on cash sales) is netted against them. Amounts are halalas.')]
class PayoutController extends Controller
{
    #[Endpoint('List payouts', 'Newest first, 25 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(CenterPayoutResource::class, CenterPayout::class, status: 200, description: 'Paginated payouts.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.view` for this center (system users: `payouts.manage`), or the token lacks `center-access:{center}`.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewPayouts', $center);

        return CenterPayoutResource::collection(
            $center->payouts()->latest('id')->paginate(25)
        );
    }

    #[Endpoint('Show a payout', 'With the ledger entries it pays.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('payout', 'integer', 'Payout ID (of this center).', example: 17)]
    #[ResponseFromApiResource(CenterPayoutResource::class, CenterPayout::class, status: 200, description: 'The payout with entries.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.view` for this center (system users: `payouts.manage`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such payout in this center.')]
    public function show(Request $request, Center $center, int $payout): CenterPayoutResource
    {
        Gate::forUser($request->user())->authorize('viewPayouts', $center);

        /** @var CenterPayout $model */
        $model = $center->payouts()->with('entries')->findOrFail($payout);

        return CenterPayoutResource::make($model);
    }

    #[Endpoint('Show the balance and payout account', 'Payouts are skipped while `account` is null; the primary user sets it.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => ['available' => 812500, 'on_hold' => 120000, 'in_pending_payouts' => 0, 'paid_out' => 4300000, 'account' => ['iban' => 'SA0380000000608010167519', 'account_name' => 'Al Fursan Stables LLC', 'bank_name' => 'Al Rajhi Bank']]], 200, 'Balance.')]
    #[ResponseField('data.available', 'integer', 'Goes into the next weekly payout, in halalas (negative carries forward).', example: 812500)]
    #[ResponseField('data.on_hold', 'integer', 'Still within the hold period, in halalas.', example: 120000)]
    #[ResponseField('data.in_pending_payouts', 'integer', 'In pending payouts awaiting transfer, in halalas.', example: 0)]
    #[ResponseField('data.paid_out', 'integer', 'Already transferred, in halalas.', example: 4300000)]
    #[ResponseField('data.account', 'object', 'Payout bank account (`iban`, `account_name`, `bank_name`); null until set.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.view` for this center (system users: `payouts.manage`), or the token lacks `center-access:{center}`.')]
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

    #[Endpoint('Set the payout bank account', 'Primary user only. Payouts already created keep the account they were created with.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => ['iban' => 'SA0380000000608010167519', 'account_name' => 'Al Fursan Stables LLC', 'bank_name' => 'Al Rajhi Bank']], 200, 'The account.')]
    #[ResponseField('data.iban', 'string', 'IBAN.', example: 'SA0380000000608010167519')]
    #[ResponseField('data.account_name', 'string', 'Account holder.', example: 'Al Fursan Stables LLC')]
    #[ResponseField('data.bank_name', 'string', 'Bank.', example: 'Al Rajhi Bank')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the center\'s primary user, or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The iban field format is invalid.', 'errors' => ['iban' => ['The iban field format is invalid.']]], 422, 'Invalid IBAN or missing fields.')]
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
