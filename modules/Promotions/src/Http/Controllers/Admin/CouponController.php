<?php

namespace Modules\Promotions\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Promotions\Http\Controllers\Concerns\ManagesCoupons;
use Modules\Promotions\Http\Requests\CouponAssignmentsRequest;
use Modules\Promotions\Http\Requests\GenerateCouponsRequest;
use Modules\Promotions\Http\Requests\StoreCouponRequest;
use Modules\Promotions\Http\Requests\UpdateCouponRequest;
use Modules\Promotions\Http\Resources\CouponResource;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;

#[Group(name: 'Admin / Promotions', description: 'Coupon codes of coupon-triggered promotions. Requires `coupons.manage`.')]
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
    public function index(Request $request, Promotion $promotion): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Coupon::class);

        return $this->listCoupons($request, $promotion);
    }

    /**
     * Create a coupon.
     *
     * Codes are upper-cased; 4-32 characters of A-Z, 0-9, `_` and `-`.
     */
    #[ApiResponse(status: 201, description: 'The created coupon.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or the promotion is not coupon-triggered.')]
    public function store(StoreCouponRequest $request, Promotion $promotion): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Coupon::class);

        return $this->createCoupon($request, $promotion);
    }

    /**
     * Generate coupons in bulk.
     *
     * Creates `count` unique random codes (optional `prefix`, `length` 6-16,
     * no look-alike characters). Up to 200 are created at once (201); larger
     * batches are queued (202). Both return the `batch_id` to list them.
     */
    #[ApiResponse(status: 201, description: '`{data: {batch_id, count, queued: false}}`.')]
    #[ApiResponse(status: 202, description: '`{data: {batch_id, count, queued: true}}`.')]
    public function generate(GenerateCouponsRequest $request, Promotion $promotion): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Coupon::class);

        return $this->generateCoupons($request, $promotion);
    }

    /**
     * Update a coupon. The code itself cannot change.
     */
    #[ApiResponse(status: 200, description: 'The updated coupon.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        Gate::forUser($request->user())->authorize('update', $coupon);

        $coupon->update($request->validated());

        return CouponResource::make($coupon->refresh());
    }

    /**
     * Assign a coupon to clients.
     *
     * For an assigned-only coupon these are the only clients who can use it.
     */
    #[ApiResponse(status: 200, description: '`{data: {assigned_clients_count}}`.')]
    public function assign(CouponAssignmentsRequest $request, Coupon $coupon): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $coupon);

        $coupon->assignedClients()->syncWithoutDetaching(
            collect($request->validated('client_ids'))->mapWithKeys(fn (int $id): array => [$id => ['assigned_at' => now()]])->all()
        );

        return response()->json(['data' => ['assigned_clients_count' => $coupon->assignedClients()->count()]]);
    }

    /**
     * Remove clients from a coupon.
     */
    #[ApiResponse(status: 200, description: '`{data: {assigned_clients_count}}`.')]
    public function unassign(CouponAssignmentsRequest $request, Coupon $coupon): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $coupon);

        $coupon->assignedClients()->detach($request->validated('client_ids'));

        return response()->json(['data' => ['assigned_clients_count' => $coupon->assignedClients()->count()]]);
    }

    /**
     * List a promotion's redemptions.
     *
     * The ledger: one row per purchase, with the discount, who funded it and
     * whether it was released (purchase cancelled or refunded).
     */
    #[QueryParameter('status', description: '`applied` or `released`.', type: 'string')]
    #[QueryParameter('client_id', description: 'Client id.', type: 'int')]
    #[ApiResponse(status: 200, description: 'Paginated `RedemptionResource` collection.')]
    public function redemptions(Request $request, Promotion $promotion): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('view', $promotion);

        return $this->listRedemptions($request, $promotion);
    }
}
