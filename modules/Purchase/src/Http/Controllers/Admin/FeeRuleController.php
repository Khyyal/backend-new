<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Purchase\Http\Requests\StoreFeeRuleRequest;
use Modules\Purchase\Http\Requests\UpdateFeeRuleRequest;
use Modules\Purchase\Http\Resources\FeeRuleResource;
use Modules\Purchase\Models\FeeRule;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Fees', description: 'Platform fees charged to centers on their purchases. A rule without `center_id` is the platform default, one without `channel` the fallback; the most specific active rule applies (center + channel, platform + channel, center fallback, platform fallback). Amounts are halalas, `percentage_bps` basis points. Requires `fees.manage`.')]
class FeeRuleController extends Controller
{
    /**
     * List fee rules.
     */
    #[QueryParameter('center_id', description: 'A center id, or `platform` for the platform defaults.', type: 'string')]
    #[QueryParameter('channel', description: 'A channel, or `fallback` for rules without one.', type: 'string')]
    #[ApiResponse(status: 200, description: 'Paginated `FeeRuleResource` collection.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', FeeRule::class);

        $rules = FeeRule::query()
            ->when($request->query('center_id'), fn ($query, $centerId) => $centerId === 'platform'
                ? $query->whereNull('center_id')
                : $query->where('center_id', $centerId))
            ->when($request->query('channel'), fn ($query, $channel) => $channel === 'fallback'
                ? $query->whereNull('channel')
                : $query->where('channel', $channel))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return FeeRuleResource::collection($rules);
    }

    /**
     * Create a fee rule.
     */
    #[ApiResponse(status: 201, description: 'The created fee rule.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or a rule already exists for this center and channel.')]
    public function store(StoreFeeRuleRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', FeeRule::class);

        $rule = FeeRule::create($request->validated());

        return FeeRuleResource::make($rule->refresh())
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a fee rule.
     */
    #[ApiResponse(status: 200, description: 'The `FeeRuleResource`.')]
    public function show(Request $request, FeeRule $feeRule): FeeRuleResource
    {
        Gate::forUser($request->user())->authorize('view', $feeRule);

        return FeeRuleResource::make($feeRule);
    }

    /**
     * Update a fee rule. Purchases already made keep the fee they were charged.
     */
    #[ApiResponse(status: 200, description: 'The updated fee rule.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or a rule already exists for this center and channel.')]
    public function update(UpdateFeeRuleRequest $request, FeeRule $feeRule): FeeRuleResource
    {
        Gate::forUser($request->user())->authorize('update', $feeRule);

        $feeRule->update($request->validated());

        return FeeRuleResource::make($feeRule->refresh());
    }

    /**
     * Delete a fee rule.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
    public function destroy(Request $request, FeeRule $feeRule): Response
    {
        Gate::forUser($request->user())->authorize('delete', $feeRule);

        $feeRule->delete();

        return response()->noContent();
    }
}
