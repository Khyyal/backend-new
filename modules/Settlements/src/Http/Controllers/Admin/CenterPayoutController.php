<?php

namespace Modules\Settlements\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Settlements\Enums\CenterPayoutStatus;
use Modules\Settlements\Http\Requests\MarkCenterPayoutPaidRequest;
use Modules\Settlements\Http\Resources\CenterPayoutResource;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Services\CenterPayoutStateService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Payouts', 'Weekly payouts of what the platform owes centers. Every Sunday 00:00 UTC a pending payout is created per center for its ledger entries past the hold period; finance transfers it and marks it paid. Amounts are halalas. Requires `payouts.manage`.')]
class CenterPayoutController extends Controller
{
    public function __construct(private CenterPayoutStateService $state) {}

    #[Endpoint('List payouts', 'Newest first, paginated.')]
    #[QueryParam('status', 'string', 'Only this status.', required: false, enum: CenterPayoutStatus::class, example: 'pending')]
    #[QueryParam('center_id', 'integer', 'Only this center.', required: false, example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(CenterPayoutResource::class, CenterPayout::class, status: 200, description: 'Paginated payouts.', paginate: 15, with: ['center'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(['message' => 'The selected status is invalid.', 'errors' => ['status' => ['The selected status is invalid.']]], 422, 'Invalid filter.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', CenterPayout::class);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(CenterPayoutStatus::class)],
            'center_id' => ['sometimes', 'integer'],
        ]);

        $payouts = CenterPayout::query()
            ->with('center')
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['center_id']), fn ($query) => $query->where('center_id', $validated['center_id']))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return CenterPayoutResource::collection($payouts);
    }

    #[Endpoint('Show a payout', 'With its ledger entries.')]
    #[UrlParam('payout', 'integer', 'Payout ID.', example: 17)]
    #[ResponseFromApiResource(CenterPayoutResource::class, CenterPayout::class, status: 200, description: 'The payout.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such payout.')]
    public function show(Request $request, CenterPayout $payout): CenterPayoutResource
    {
        Gate::forUser($request->user())->authorize('view', $payout);

        return CenterPayoutResource::make($payout->load(['center', 'entries']));
    }

    #[Endpoint('Mark a payout as transferred', 'Only pending payouts.')]
    #[UrlParam('payout', 'integer', 'Payout ID.', example: 17)]
    #[ResponseFromApiResource(CenterPayoutResource::class, CenterPayout::class, status: 200, description: 'The paid payout.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such payout.')]
    #[ApiResponse(['message' => 'Cannot change a payout with status "paid". Only pending payouts can be paid or cancelled.', 'errors' => ['status' => ['Cannot change a payout with status "paid". Only pending payouts can be paid or cancelled.']]], 422, 'Missing reference, or the payout is not pending.')]
    public function markPaid(MarkCenterPayoutPaidRequest $request, CenterPayout $payout): CenterPayoutResource
    {
        Gate::forUser($request->user())->authorize('update', $payout);

        return CenterPayoutResource::make($this->transition(
            fn (): CenterPayout => $this->state->markPaid($payout, $request->validated('reference'), $request->user()),
        )->load('center'));
    }

    #[Endpoint('Cancel a payout', 'Only pending payouts; its entries go into the next one.')]
    #[UrlParam('payout', 'integer', 'Payout ID.', example: 17)]
    #[ResponseFromApiResource(CenterPayoutResource::class, CenterPayout::class, status: 200, description: 'The cancelled payout.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such payout.')]
    #[ApiResponse(['message' => 'Cannot change a payout with status "paid". Only pending payouts can be paid or cancelled.', 'errors' => ['status' => ['Cannot change a payout with status "paid". Only pending payouts can be paid or cancelled.']]], 422, 'The payout is not pending.')]
    public function cancel(Request $request, CenterPayout $payout): CenterPayoutResource
    {
        Gate::forUser($request->user())->authorize('update', $payout);

        return CenterPayoutResource::make($this->transition(
            fn (): CenterPayout => $this->state->cancel($payout),
        )->load('center'));
    }

    /**
     * @param  callable(): CenterPayout  $change
     */
    private function transition(callable $change): CenterPayout
    {
        try {
            return $change();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
    }
}
