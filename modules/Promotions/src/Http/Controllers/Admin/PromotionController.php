<?php

namespace Modules\Promotions\Http\Controllers\Admin;

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
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Http\Requests\StorePromotionRequest;
use Modules\Promotions\Http\Requests\SyncPromotionSchedulesRequest;
use Modules\Promotions\Http\Requests\SyncPromotionTargetsRequest;
use Modules\Promotions\Http\Requests\UpdatePromotionRequest;
use Modules\Promotions\Http\Resources\PromotionResource;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\PromotionService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Promotions', 'Platform-wide and center promotions. Amounts are halalas; percentage values are basis points (1250 = 12.5%). Requires the `promotions.*` permissions.')]
class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotions,
    ) {}

    #[Endpoint('List promotions', 'Newest first, paginated (`data`, `links`, `meta`). Includes platform-wide and center promotions.')]
    #[QueryParam('status', 'string', 'Filter by status.', required: false, enum: PromotionStatus::class, example: 'active')]
    #[QueryParam('scope', 'string', 'Filter by scope.', required: false, enum: PromotionScope::class, example: 'item')]
    #[QueryParam('trigger', 'string', 'Filter by trigger.', required: false, enum: PromotionTrigger::class, example: 'automatic')]
    #[QueryParam('center_id', 'string', 'A center ID, or `platform` for platform-wide promotions only.', required: false, example: '8')]
    #[QueryParam('campaign_id', 'integer', 'Filter by campaign ID.', required: false, example: 3)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'Paginated promotions.', paginate: 15, withCount: ['coupons'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Promotion::class);

        $promotions = Promotion::query()
            ->with('media')
            ->withCount('coupons')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('scope'), fn ($query, $scope) => $query->where('scope', $scope))
            ->when($request->query('trigger'), fn ($query, $trigger) => $query->where('trigger', $trigger))
            ->when($request->query('center_id'), fn ($query, $centerId) => $centerId === 'platform'
                ? $query->whereNull('center_id')
                : $query->where('center_id', $centerId))
            ->when($request->query('campaign_id'), fn ($query, $campaignId) => $query->where('campaign_id', $campaignId))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return PromotionResource::collection($promotions);
    }

    #[Endpoint('Create a promotion', 'Created as `draft`. `center_id` null makes it platform-wide. `funded_by` says who bears the discount; `center_share_bps` is required for `shared`.')]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 201, description: 'The created promotion, with targets and schedules.', with: ['targets', 'schedules'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(['message' => 'The name.en field is required. (and 1 more error)', 'errors' => ['name.en' => ['The name.en field is required.'], 'discount_type' => ['The selected discount type is invalid.']]], 422, 'Validation failed (fields, `fixed_price` with order scope, percentage above 10000).')]
    public function store(StorePromotionRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Promotion::class);

        $promotion = $this->promotions->create($request->promotionData(), $request->user());

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media']))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a promotion', 'With targets, schedules, cover and coupon count.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'The promotion.', with: ['targets', 'schedules'], withCount: ['coupons'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    public function show(Request $request, Promotion $promotion): PromotionResource
    {
        Gate::forUser($request->user())->authorize('view', $promotion);

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media'])->loadCount('coupons'));
    }

    #[Endpoint('Update a promotion', 'Partial update: send only the fields to change. Once a purchase has used it, `scope`, `discount_type`, `discount_value` and `max_discount_amount` are locked: archive it and create a new one instead. Archived promotions cannot change.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'The updated promotion.', with: ['targets', 'schedules'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    #[ApiResponse(['message' => 'This promotion has already been used: archive it and create a new one to change its value.', 'errors' => ['discount_value' => ['This promotion has already been used: archive it and create a new one to change its value.']]], 422, 'Validation failed, the promotion is archived, or a locked field changed after use.')]
    public function update(UpdatePromotionRequest $request, Promotion $promotion): PromotionResource
    {
        Gate::forUser($request->user())->authorize('update', $promotion);

        $promotion = $this->promotions->update($promotion, $request->promotionData(), $request->user());

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media']));
    }

    #[Endpoint('Delete a promotion', 'Soft delete: redemptions and purchase snapshots keep their history.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    public function destroy(Request $request, Promotion $promotion): Response
    {
        Gate::forUser($request->user())->authorize('delete', $promotion);

        $this->promotions->delete($promotion);

        return response()->noContent();
    }

    #[Endpoint('Activate a promotion', 'Allowed from `draft` or `paused`.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'The promotion, now `active`.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    #[ApiResponse(['message' => 'A promotion cannot move from "archived" to "active".', 'errors' => ['status' => ['A promotion cannot move from "archived" to "active".']]], 422, 'Not allowed from the current status.')]
    public function activate(Request $request, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $promotion, PromotionStatus::Active);
    }

    #[Endpoint('Pause a promotion', 'Allowed from `active`.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'The promotion, now `paused`.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    #[ApiResponse(['message' => 'A promotion cannot move from "archived" to "active".', 'errors' => ['status' => ['A promotion cannot move from "archived" to "active".']]], 422, 'Not allowed from the current status.')]
    public function pause(Request $request, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $promotion, PromotionStatus::Paused);
    }

    #[Endpoint('Archive a promotion', 'Final: archived promotions can no longer change.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'The promotion, now `archived`.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    #[ApiResponse(['message' => 'A promotion cannot move from "archived" to "active".', 'errors' => ['status' => ['A promotion cannot move from "archived" to "active".']]], 422, 'Already archived.')]
    public function archive(Request $request, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $promotion, PromotionStatus::Archived);
    }

    #[Endpoint('Replace the promotion\'s targets', 'Targets are centers or services. Mode `include` (default) or `exclude`. With no include targets the promotion applies to everything it can reach. Locked once the promotion has been used.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'The promotion with its new targets and schedules.', with: ['targets', 'schedules'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    #[ApiResponse(['message' => 'The selected target is invalid.', 'errors' => ['targets.0.id' => ['The selected target is invalid.']]], 422, 'Validation failed (unknown or duplicate target), or the promotion was already used.')]
    public function syncTargets(SyncPromotionTargetsRequest $request, Promotion $promotion): PromotionResource
    {
        Gate::forUser($request->user())->authorize('update', $promotion);

        return PromotionResource::make($this->promotions->syncTargets($promotion, $request->validated('targets'))->load('schedules'));
    }

    #[Endpoint('Replace the promotion\'s weekly schedule', '`day_of_week` 1 = Monday … 7 = Sunday, times `HH:MM` UTC. An end before the start wraps past midnight. With no windows the promotion is live for its whole date range.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(PromotionResource::class, Promotion::class, status: 200, description: 'The promotion with its new schedules and targets.', with: ['targets', 'schedules'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the required `promotions.*` permission.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion not found.')]
    #[ApiResponse(['message' => 'The schedules.0.end_time field and schedules.0.start_time must be different.', 'errors' => ['schedules.0.end_time' => ['The schedules.0.end_time field and schedules.0.start_time must be different.']]], 422, 'Validation failed.')]
    public function syncSchedules(SyncPromotionSchedulesRequest $request, Promotion $promotion): PromotionResource
    {
        Gate::forUser($request->user())->authorize('update', $promotion);

        return PromotionResource::make($this->promotions->syncSchedules($promotion, $request->validated('schedules'))->load('targets'));
    }

    private function transition(Request $request, Promotion $promotion, PromotionStatus $status): PromotionResource
    {
        Gate::forUser($request->user())->authorize('activate', $promotion);

        return PromotionResource::make($this->promotions->transition($promotion, $status));
    }
}
