<?php

namespace Modules\Promotions\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Promotions\Http\Controllers\Concerns\ManagesCoupons;
use Modules\Promotions\Http\Requests\GenerateCouponsRequest;
use Modules\Promotions\Http\Requests\StoreCouponRequest;
use Modules\Promotions\Http\Requests\UpdateCouponRequest;
use Modules\Promotions\Http\Resources\CouponResource;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;

#[Group(name: 'Center / Promotions', description: "Coupon codes of the center's coupon-triggered promotions. Requires `promotions.manage`.")]
class CouponController extends Controller
{
    use ManagesCoupons;

    /**
     * List a promotion's coupons.
     */
    #[QueryParameter('status', description: '`active` or `disabled`.', type: 'string')]
    #[QueryParameter('code', description: 'Exact code.', type: 'string')]
    #[QueryParameter('batch_id', description: 'Generation batch.', type: 'string')]
    #[ApiResponse(status: 200, description: 'Paginated `CouponResource` collection.')]
    public function index(Request $request, Center $center, Promotion $promotion): AnonymousResourceCollection
    {
        $this->authorizeCenter($request, $center);

        return $this->listCoupons($request, $promotion);
    }

    /**
     * Create a coupon.
     *
     * Codes are upper-cased; 4-32 characters of A-Z, 0-9, `_` and `-`.
     */
    #[ApiResponse(status: 201, description: 'The created coupon.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or the promotion is not coupon-triggered.')]
    public function store(StoreCouponRequest $request, Center $center, Promotion $promotion): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        return $this->createCoupon($request, $promotion);
    }

    /**
     * Generate coupons in bulk.
     *
     * Up to 200 are created at once (201); larger batches are queued (202).
     */
    #[ApiResponse(status: 201, description: '`{data: {batch_id, count, queued: false}}`.')]
    #[ApiResponse(status: 202, description: '`{data: {batch_id, count, queued: true}}`.')]
    public function generate(GenerateCouponsRequest $request, Center $center, Promotion $promotion): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        return $this->generateCoupons($request, $promotion);
    }

    /**
     * Update a coupon. The code itself cannot change.
     */
    #[ApiResponse(status: 200, description: 'The updated coupon.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function update(UpdateCouponRequest $request, Center $center, Promotion $promotion, Coupon $coupon): CouponResource
    {
        $this->authorizeCenter($request, $center);

        $coupon->update($request->validated());

        return CouponResource::make($coupon->refresh());
    }

    /**
     * List a promotion's redemptions.
     */
    #[QueryParameter('status', description: '`applied` or `released`.', type: 'string')]
    #[ApiResponse(status: 200, description: 'Paginated `RedemptionResource` collection.')]
    public function redemptions(Request $request, Center $center, Promotion $promotion): AnonymousResourceCollection
    {
        $this->authorizeCenter($request, $center);

        return $this->listRedemptions($request, $promotion);
    }

    private function authorizeCenter(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('managePromotions', $center);
    }
}
