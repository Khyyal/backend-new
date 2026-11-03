<?php

namespace Modules\Promotions\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Http\Requests\StorePromotionRequest;
use Modules\Promotions\Http\Requests\SyncPromotionSchedulesRequest;
use Modules\Promotions\Http\Requests\SyncPromotionTargetsRequest;
use Modules\Promotions\Http\Requests\UpdatePromotionRequest;
use Modules\Promotions\Http\Resources\PromotionResource;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\PromotionService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Promotions', description: "The center's own promotions, funded by the center. Amounts are minor units (halalas); percentage values are basis points (1250 = 12.5%). Requires `promotions.manage`.")]
class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotions,
    ) {}

    /**
     * List the center's promotions.
     */
    #[QueryParameter('status', description: '`draft`, `active`, `paused` or `archived`.', type: 'string')]
    #[QueryParameter('trigger', description: '`automatic` or `coupon`.', type: 'string')]
    #[ApiResponse(status: 200, description: 'Paginated `PromotionResource` collection.')]
    #[ApiResponse(status: 403, description: 'Missing `promotions.manage`.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        $this->authorizeCenter($request, $center);

        $promotions = $center->promotions()
            ->with('media')
            ->withCount('coupons')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('trigger'), fn ($query, $trigger) => $query->where('trigger', $trigger))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return PromotionResource::collection($promotions);
    }

    /**
     * Create a promotion. Created as a draft.
     */
    #[ApiResponse(status: 201, description: 'The created promotion.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function store(StorePromotionRequest $request, Center $center): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        $promotion = $this->promotions->create($request->promotionData(), $request->user());

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media']))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a promotion.
     */
    #[ApiResponse(status: 200, description: 'The `PromotionResource` with targets and schedules.')]
    #[ApiResponse(status: 404, description: 'Not a promotion of this center.')]
    public function show(Request $request, Center $center, Promotion $promotion): PromotionResource
    {
        $this->authorizeCenter($request, $center);

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media'])->loadCount('coupons'));
    }

    /**
     * Update a promotion.
     *
     * Once a purchase has used it, its scope, discount type, value and cap are locked.
     */
    #[ApiResponse(status: 200, description: 'The updated promotion.')]
    #[ApiResponse(status: 422, description: 'Validation failed, the promotion is archived, or a locked field changed.')]
    public function update(UpdatePromotionRequest $request, Center $center, Promotion $promotion): PromotionResource
    {
        $this->authorizeCenter($request, $center);

        $promotion = $this->promotions->update($promotion, $request->promotionData(), $request->user());

        return PromotionResource::make($promotion->load(['targets', 'schedules', 'media']));
    }

    /**
     * Delete a promotion.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
    public function destroy(Request $request, Center $center, Promotion $promotion): Response
    {
        $this->authorizeCenter($request, $center);

        $this->promotions->delete($promotion);

        return response()->noContent();
    }

    /**
     * Activate a promotion (from draft or paused).
     */
    #[ApiResponse(status: 200, description: 'The activated promotion.')]
    #[ApiResponse(status: 422, description: 'Not allowed from the current status.')]
    public function activate(Request $request, Center $center, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $center, $promotion, PromotionStatus::Active);
    }

    /**
     * Pause an active promotion.
     */
    #[ApiResponse(status: 200, description: 'The paused promotion.')]
    #[ApiResponse(status: 422, description: 'Not allowed from the current status.')]
    public function pause(Request $request, Center $center, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $center, $promotion, PromotionStatus::Paused);
    }

    /**
     * Archive a promotion.
     */
    #[ApiResponse(status: 200, description: 'The archived promotion.')]
    #[ApiResponse(status: 422, description: 'Already archived.')]
    public function archive(Request $request, Center $center, Promotion $promotion): PromotionResource
    {
        return $this->transition($request, $center, $promotion, PromotionStatus::Archived);
    }

    /**
     * Replace the promotion's targets.
     *
     * Only `service` targets of this center; mode `include` (default) or
     * `exclude`. With no include targets every service of the center applies.
     */
    #[ApiResponse(status: 200, description: 'The promotion with its new targets.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or the promotion was already used.')]
    public function syncTargets(SyncPromotionTargetsRequest $request, Center $center, Promotion $promotion): PromotionResource
    {
        $this->authorizeCenter($request, $center);

        return PromotionResource::make($this->promotions->syncTargets($promotion, $request->validated('targets'))->load('schedules'));
    }

    /**
     * Replace the promotion's weekly schedule.
     *
     * `day_of_week` 1 = Monday … 7 = Sunday, times `HH:MM`. An end before the
     * start wraps past midnight.
     */
    #[ApiResponse(status: 200, description: 'The promotion with its new schedule.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function syncSchedules(SyncPromotionSchedulesRequest $request, Center $center, Promotion $promotion): PromotionResource
    {
        $this->authorizeCenter($request, $center);

        return PromotionResource::make($this->promotions->syncSchedules($promotion, $request->validated('schedules'))->load('targets'));
    }

    private function transition(Request $request, Center $center, Promotion $promotion, PromotionStatus $status): PromotionResource
    {
        $this->authorizeCenter($request, $center);

        return PromotionResource::make($this->promotions->transition($promotion, $status));
    }

    private function authorizeCenter(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('managePromotions', $center);
    }
}
