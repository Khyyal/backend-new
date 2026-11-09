<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Purchase\Http\Requests\StoreFeeRuleRequest;
use Modules\Purchase\Http\Requests\UpdateFeeRuleRequest;
use Modules\Purchase\Http\Resources\FeeRuleResource;
use Modules\Purchase\Models\FeeRule;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Fees', 'Platform fees charged to centers on their purchases. A rule without `center_id` is the platform default, one without `channel` the fallback; the most specific active rule applies (center + channel, platform + channel, center fallback, platform fallback). Requires `fees.manage`.')]
class FeeRuleController extends Controller
{
    #[Endpoint('List fee rules', 'Newest first, paginated.')]
    #[QueryParam('center_id', 'string', 'A center ID, or `platform` for the platform defaults.', required: false, example: '8')]
    #[QueryParam('channel', 'string', 'A channel, or `fallback` for rules without one.', required: false, example: 'khyyal_app')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(FeeRuleResource::class, FeeRule::class, status: 200, description: 'Paginated rules.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `fees.manage`.')]
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

    #[Endpoint('Create a fee rule')]
    #[ResponseFromApiResource(FeeRuleResource::class, FeeRule::class, status: 201, description: 'The created rule.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `fees.manage`.')]
    #[ApiResponse(['message' => 'The channel has already been taken.', 'errors' => ['channel' => ['The channel has already been taken.']]], 422, 'Validation failed, or a rule already exists for this center and channel.')]
    public function store(StoreFeeRuleRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', FeeRule::class);

        $rule = FeeRule::create($request->validated());

        return FeeRuleResource::make($rule->refresh())
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a fee rule')]
    #[UrlParam('fee_rule', 'integer', 'Fee rule ID.', example: 2)]
    #[ResponseFromApiResource(FeeRuleResource::class, FeeRule::class, status: 200, description: 'The rule.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `fees.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such fee rule.')]
    public function show(Request $request, FeeRule $feeRule): FeeRuleResource
    {
        Gate::forUser($request->user())->authorize('view', $feeRule);

        return FeeRuleResource::make($feeRule);
    }

    #[Endpoint('Update a fee rule', 'Purchases already made keep the fee they were charged.')]
    #[UrlParam('fee_rule', 'integer', 'Fee rule ID.', example: 2)]
    #[ResponseFromApiResource(FeeRuleResource::class, FeeRule::class, status: 200, description: 'The updated rule.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `fees.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such fee rule.')]
    #[ApiResponse(['message' => 'The percentage bps field must not be greater than 10000.', 'errors' => ['percentage_bps' => ['The percentage bps field must not be greater than 10000.']]], 422, 'Validation failed, or a rule already exists for this center and channel.')]
    public function update(UpdateFeeRuleRequest $request, FeeRule $feeRule): FeeRuleResource
    {
        Gate::forUser($request->user())->authorize('update', $feeRule);

        $feeRule->update($request->validated());

        return FeeRuleResource::make($feeRule->refresh());
    }

    #[Endpoint('Delete a fee rule')]
    #[UrlParam('fee_rule', 'integer', 'Fee rule ID.', example: 2)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `fees.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such fee rule.')]
    public function destroy(Request $request, FeeRule $feeRule): Response
    {
        Gate::forUser($request->user())->authorize('delete', $feeRule);

        $feeRule->delete();

        return response()->noContent();
    }
}
