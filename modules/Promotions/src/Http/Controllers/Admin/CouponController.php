<?php

namespace Modules\Promotions\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
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
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Enums\RedemptionStatus;
use Modules\Promotions\Http\Controllers\Concerns\ManagesCoupons;
use Modules\Promotions\Http\Requests\CouponAssignmentsRequest;
use Modules\Promotions\Http\Requests\GenerateCouponsRequest;
use Modules\Promotions\Http\Requests\StoreCouponRequest;
use Modules\Promotions\Http\Requests\UpdateCouponRequest;
use Modules\Promotions\Http\Resources\CouponResource;
use Modules\Promotions\Http\Resources\RedemptionResource;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Models\PromotionRedemption;
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Coupons', 'Coupon codes and the redemption ledger of coupon-triggered promotions. Requires `coupons.manage`.')]
class CouponController extends Controller
{
    use ManagesCoupons;

    #[Endpoint('List a promotion\'s coupons', 'Newest first, paginated, with assigned-client counts.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[QueryParam('status', 'string', 'Filter by status.', required: false, enum: CouponStatus::class, example: 'active')]
    #[QueryParam('code', 'string', 'Exact code (case-insensitive).', required: false, example: 'SUMMER25')]
    #[QueryParam('batch_id', 'string', 'Only codes of this generation batch.', required: false, example: '01J0Z8Q4M6T2W9A7C5V3B1N8XK')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(CouponResource::class, Coupon::class, status: 200, description: 'Paginated coupons.', paginate: 15, withCount: ['assignedClients'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `coupons.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found.')]
    public function index(Request $request, Promotion $promotion): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Coupon::class);

        return $this->listCoupons($request, $promotion);
    }

    #[Endpoint('Create a coupon', 'Only for `coupon`-triggered promotions. Codes are upper-cased; 4-32 characters of A-Z, 0-9, `_` and `-`.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseFromApiResource(CouponResource::class, Coupon::class, status: 201, description: 'The created coupon.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `coupons.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found.')]
    #[ApiResponse(['message' => 'The code has already been taken.', 'errors' => ['code' => ['The code has already been taken.']]], 422, 'Validation failed (e.g. code taken), or the promotion is not coupon-triggered.')]
    public function store(StoreCouponRequest $request, Promotion $promotion): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Coupon::class);

        return $this->createCoupon($request, $promotion);
    }

    #[Endpoint('Generate coupons in bulk', 'Creates `count` unique random codes (optional `prefix`, `length` 6-16, no look-alike characters). Up to 200 are created in the request (201); larger batches are queued (202). Both return the `batch_id` to list them.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[ApiResponse(['data' => ['batch_id' => '01J0Z8Q4M6T2W9A7C5V3B1N8XK', 'count' => 50, 'queued' => false]], 201, 'Codes created in the request.')]
    #[ApiResponse(['data' => ['batch_id' => '01J0Z8Q4M6T2W9A7C5V3B1N8XK', 'count' => 5000, 'queued' => true]], 202, 'Generation queued.')]
    #[ResponseField('data.batch_id', 'string', 'Batch identifier; list the codes with `GET …/coupons?batch_id=`.', example: '01J0Z8Q4M6T2W9A7C5V3B1N8XK')]
    #[ResponseField('data.count', 'integer', 'Codes created (inline) or requested (queued).', example: 50)]
    #[ResponseField('data.queued', 'boolean', 'True when generation runs in the background (more than 200 codes).', example: false)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `coupons.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found.')]
    #[ApiResponse(['message' => 'This promotion is not triggered by a coupon code.', 'errors' => ['promotion' => ['This promotion is not triggered by a coupon code.']]], 422, 'Validation failed, or the promotion is not coupon-triggered.')]
    public function generate(GenerateCouponsRequest $request, Promotion $promotion): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Coupon::class);

        return $this->generateCoupons($request, $promotion);
    }

    #[Endpoint('Update a coupon', 'Partial update of limits, window, status and assignment mode. The code itself cannot change.')]
    #[UrlParam('coupon', 'integer', 'Coupon ID.', example: 41)]
    #[ResponseFromApiResource(CouponResource::class, Coupon::class, status: 200, description: 'The updated coupon.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `coupons.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found.')]
    #[ApiResponse(['message' => 'The code field is prohibited.', 'errors' => ['code' => ['The code field is prohibited.']]], 422, 'Validation failed (e.g. `code` sent).')]
    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        Gate::forUser($request->user())->authorize('update', $coupon);

        $coupon->update($request->validated());

        return CouponResource::make($coupon->refresh());
    }

    #[Endpoint('Assign a coupon to clients', 'Adds clients without removing existing ones. For an assigned-only coupon these are the only clients who can use it.')]
    #[UrlParam('coupon', 'integer', 'Coupon ID.', example: 41)]
    #[ApiResponse(['data' => ['assigned_clients_count' => 2]], 200, 'Assigned-client count after the change.')]
    #[ResponseField('data.assigned_clients_count', 'integer', 'Clients now assigned to the coupon.', example: 2)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `coupons.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Coupon not found.')]
    #[ApiResponse(['message' => 'The selected client_ids.0 is invalid.', 'errors' => ['client_ids.0' => ['The selected client_ids.0 is invalid.']]], 422, 'Validation failed (empty, duplicate or unknown client IDs).')]
    public function assign(CouponAssignmentsRequest $request, Coupon $coupon): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $coupon);

        $coupon->assignedClients()->syncWithoutDetaching(
            collect($request->validated('client_ids'))->mapWithKeys(fn (int $id): array => [$id => ['assigned_at' => now()]])->all()
        );

        return response()->json(['data' => ['assigned_clients_count' => $coupon->assignedClients()->count()]]);
    }

    #[Endpoint('Remove clients from a coupon', 'Unknown or unassigned IDs are ignored.')]
    #[UrlParam('coupon', 'integer', 'Coupon ID.', example: 41)]
    #[ApiResponse(['data' => ['assigned_clients_count' => 2]], 200, 'Assigned-client count after the change.')]
    #[ResponseField('data.assigned_clients_count', 'integer', 'Clients now assigned to the coupon.', example: 2)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `coupons.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Coupon not found.')]
    #[ApiResponse(['message' => 'The client ids field is required.', 'errors' => ['client_ids' => ['The client ids field is required.']]], 422, 'Validation failed.')]
    public function unassign(CouponAssignmentsRequest $request, Coupon $coupon): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $coupon);

        $coupon->assignedClients()->detach($request->validated('client_ids'));

        return response()->json(['data' => ['assigned_clients_count' => $coupon->assignedClients()->count()]]);
    }

    #[Endpoint('List a promotion\'s redemptions', 'The ledger: one row per purchase, with the discount, who funded it and whether it was released (purchase cancelled or refunded). Newest first, paginated.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[QueryParam('status', 'string', 'Filter by status.', required: false, enum: RedemptionStatus::class, example: 'applied')]
    #[QueryParam('client_id', 'integer', 'Only this client\'s redemptions.', required: false, example: 77)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(RedemptionResource::class, PromotionRedemption::class, status: 200, description: 'Paginated redemptions.', paginate: 15, with: ['coupon'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `coupons.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found.')]
    public function redemptions(Request $request, Promotion $promotion): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('view', $promotion);

        return $this->listRedemptions($request, $promotion);
    }
}
