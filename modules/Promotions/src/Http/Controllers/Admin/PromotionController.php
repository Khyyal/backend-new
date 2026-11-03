<?php

namespace Modules\Promotions\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Http\Requests\StorePromotionRequest;
use Modules\Promotions\Http\Requests\SyncPromotionSchedulesRequest;
use Modules\Promotions\Http\Requests\SyncPromotionTargetsRequest;
use Modules\Promotions\Http\Requests\UpdatePromotionRequest;
use Modules\Promotions\Http\Resources\PromotionResource;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\PromotionService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Promotions', description: 'Platform-wide and center promotions. Amounts are minor units (halalas); percentage values are basis points (1250 = 12.5%). Requires the `promotions.*` permissions.')]
class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotions,
    ) {}

    /**
     * List promotions.
     */
    #[QueryParameter('status', description: '`draft`, `active`, `paused` or `archived`.', type: 'string')]
    #[QueryParameter('scope', description: '`item` or `order`.', type: 'string')]
    #[QueryParameter('trigger', description: '`automatic` or `coupon`.', type: 'string')]
    #[QueryParameter('center_id', description: 'A center id, or `platform` for platform-wide promotions.', type: 'string')]
    #[QueryParameter('campaign_id', description: 'Campaign id.', type: 'int')]
    #[ApiResponse(status: 200, description: 'Paginated `PromotionResource` collection.')]
    #[ApiResponse(status: 403, description: 'Missing `promotions.view_any`.')]
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

    /**
     * Create a promotion.
     *
     * Created as a draft. `center_id` null makes it platform-wide. `funded_by`
     * says who bears the discount; `center_share_bps` is required for `shared`.
     */
    #[ApiResponse(status: 201, description: 'The created promotion.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function store(StorePromotionRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Promotion::class);

        $promotion = $this->promotions->create($request->promotionData(), $request->user());

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media']))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a promotion.
     */
    #[ApiResponse(status: 200, description: 'The `PromotionResource` with targets and schedules.')]
    #[ApiResponse(status: 404, description: 'Not found.')]
    public function show(Request $request, Promotion $promotion): PromotionResource
    {
        Gate::forUser($request->user())->authorize('view', $promotion);

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media'])->loadCount('coupons'));
    }

    /**
     * Update a promotion.
     *
     * Once a purchase has used it, its scope, discount type, value and cap are
     * locked: archive it and create a new one instead.
     */
    #[ApiResponse(status: 200, description: 'The updated promotion.')]
    #[ApiResponse(status: 422, description: 'Validation failed, the promotion is archived, or a locked field changed.')]
    public function update(UpdatePromotionRequest $request, Promotion $promotion): PromotionResource
    {
        Gate::forUser($request->user())->authorize('update', $promotion);

        $promotion = $this->promotions->update($promotion, $request->promotionData(), $request->user());

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media']));
    }

    /**
     * Delete a promotion.
     *
     * Soft delete: redemptions and purchase snapshots keep their history.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
    public function destroy(Request $request, Promotion $promotion): Response
    {
        Gate::forUser($request->user())->authorize('delete', $promotion);

        $this->promotions->delete($promotion);

        return response()->noContent();
    }

    /**
     * Activate a promotion (from draft or paused).
     */
    #[ApiResponse(status: 200, description: 'The activated promotion.')]
    #[ApiResponse(status: 422, description: 'Not allowed from the current status.')]
    public function activate(Request $request, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $promotion, PromotionStatus::Active);
    }

    /**
     * Pause an active promotion.
     */
    #[ApiResponse(status: 200, description: 'The paused promotion.')]
    #[ApiResponse(status: 422, description: 'Not allowed from the current status.')]
    public function pause(Request $request, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $promotion, PromotionStatus::Paused);
    }

    /**
     * Archive a promotion. Archived promotions can no longer change.
     */
    #[ApiResponse(status: 200, description: 'The archived promotion.')]
    #[ApiResponse(status: 422, description: 'Already archived.')]
    public function archive(Request $request, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $promotion, PromotionStatus::Archived);
    }

    /**
     * Replace the promotion's targets.
     *
     * Types: `center`, `service`; mode `include` (default) or `exclude`. With
     * no include targets the promotion applies to every service it can reach.
     */
    #[ApiResponse(status: 200, description: 'The promotion with its new targets.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or the promotion was already used.')]
    public function syncTargets(SyncPromotionTargetsRequest $request, Promotion $promotion): PromotionResource
    {
        Gate::forUser($request->user())->authorize('update', $promotion);

        return PromotionResource::make($this->promotions->syncTargets($promotion, $request->validated('targets'))->load('schedules'));
    }

    /**
     * Replace the promotion's weekly schedule.
     *
     * `day_of_week` 1 = Monday … 7 = Sunday, times `HH:MM` in the application
     * timezone. An end before the start wraps past midnight. With no windows
     * the promotion is live for its whole date range.
     */
    #[ApiResponse(status: 200, description: 'The promotion with its new schedule.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
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
