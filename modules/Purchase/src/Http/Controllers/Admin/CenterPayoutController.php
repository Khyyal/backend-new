<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Purchase\Enums\CenterPayoutStatus;
use Modules\Purchase\Http\Requests\MarkCenterPayoutPaidRequest;
use Modules\Purchase\Http\Resources\CenterPayoutResource;
use Modules\Purchase\Models\CenterPayout;
use Modules\Purchase\Services\CenterPayoutStateService;

#[Group(name: 'Admin / Payouts', description: 'Weekly payouts of what the platform owes centers. Every Sunday 00:00 UTC a pending payout is created per center for its ledger entries past the hold period; finance transfers it and marks it paid. Amounts are halalas. Requires `payouts.manage`.')]
class CenterPayoutController extends Controller
{
    public function __construct(private CenterPayoutStateService $state) {}

    /**
     * List payouts.
     */
    #[QueryParameter('status', description: '`pending`, `paid` or `cancelled`.', type: 'string')]
    #[QueryParameter('center_id', description: 'Only this center\'s payouts.', type: 'int')]
    #[ApiResponse(status: 200, description: 'Paginated `CenterPayoutResource` collection, newest first.')]
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

    /**
     * Show a payout with its ledger entries.
     */
    #[ApiResponse(status: 200, description: 'The `CenterPayoutResource` with `entries`.')]
    public function show(Request $request, CenterPayout $payout): CenterPayoutResource
    {
        Gate::forUser($request->user())->authorize('view', $payout);

        return CenterPayoutResource::make($payout->load(['center', 'entries']));
    }

    /**
     * Mark a pending payout as transferred.
     */
    #[ApiResponse(status: 200, description: 'The paid payout.')]
    #[ApiResponse(status: 422, description: 'Missing reference, or the payout is not pending.')]
    public function markPaid(MarkCenterPayoutPaidRequest $request, CenterPayout $payout): CenterPayoutResource
    {
        Gate::forUser($request->user())->authorize('update', $payout);

        return CenterPayoutResource::make($this->transition(
            fn (): CenterPayout => $this->state->markPaid($payout, $request->validated('reference'), $request->user()),
        )->load('center'));
    }

    /**
     * Cancel a pending payout; its entries go into the next one.
     */
    #[ApiResponse(status: 200, description: 'The cancelled payout.')]
    #[ApiResponse(status: 422, description: 'The payout is not pending.')]
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
