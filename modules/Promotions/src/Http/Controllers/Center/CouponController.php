<?php

namespace Modules\Promotions\Http\Controllers\Center;

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
use Modules\Centers\Models\Center;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Enums\RedemptionStatus;
use Modules\Promotions\Http\Controllers\Concerns\ManagesCoupons;
use Modules\Promotions\Http\Requests\GenerateCouponsRequest;
use Modules\Promotions\Http\Requests\StoreCouponRequest;
use Modules\Promotions\Http\Requests\UpdateCouponRequest;
use Modules\Promotions\Http\Resources\CouponResource;
use Modules\Promotions\Http\Resources\RedemptionResource;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Models\PromotionRedemption;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Coupons', 'Coupon codes and redemptions of the center\'s coupon-triggered promotions. Requires the center permission `promotions.manage`.')]
class CouponController extends Controller
{
    use ManagesCoupons;

    #[Endpoint('List a promotion\'s coupons', 'Newest first, paginated, with assigned-client counts.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('promotion', 'integer', 'Promotion ID (must belong to the center).', example: 12)]
    #[QueryParam('status', 'string', 'Filter by status.', required: false, enum: CouponStatus::class, example: 'active')]
    #[QueryParam('code', 'string', 'Exact code (case-insensitive).', required: false, example: 'SUMMER25')]
    #[QueryParam('batch_id', 'string', 'Only codes of this generation batch.', required: false, example: '01J0Z8Q4M6T2W9A7C5V3B1N8XK')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(CouponResource::class, Coupon::class, status: 200, description: 'Paginated coupons.', paginate: 15, withCount: ['assignedClients'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `promotions.manage` for this center, the token lacks `center-access:{center}`, or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found in this center.')]
    public function index(Request $request, Center $center, Promotion $promotion): AnonymousResourceCollection
    {
        $this->authorizeCenter($request, $center);

        return $this->listCoupons($request, $promotion);
    }

    #[Endpoint('Create a coupon', 'Only for `coupon`-triggered promotions. Codes are upper-cased; 4-32 characters of A-Z, 0-9, `_` and `-`.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('promotion', 'integer', 'Promotion ID (must belong to the center).', example: 12)]
    #[ResponseFromApiResource(CouponResource::class, Coupon::class, status: 201, description: 'The created coupon.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `promotions.manage` for this center, the token lacks `center-access:{center}`, or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found in this center.')]
    #[ApiResponse(['message' => 'The code has already been taken.', 'errors' => ['code' => ['The code has already been taken.']]], 422, 'Validation failed (e.g. code taken), or the promotion is not coupon-triggered.')]
    public function store(StoreCouponRequest $request, Center $center, Promotion $promotion): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        return $this->createCoupon($request, $promotion);
    }

    #[Endpoint('Generate coupons in bulk', 'Creates `count` unique random codes (optional `prefix`, `length` 6-16, no look-alike characters). Up to 200 are created in the request (201); larger batches are queued (202). Both return the `batch_id` to list them.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('promotion', 'integer', 'Promotion ID (must belong to the center).', example: 12)]
    #[ApiResponse(['data' => ['batch_id' => '01J0Z8Q4M6T2W9A7C5V3B1N8XK', 'count' => 50, 'queued' => false]], 201, 'Codes created in the request.')]
    #[ApiResponse(['data' => ['batch_id' => '01J0Z8Q4M6T2W9A7C5V3B1N8XK', 'count' => 5000, 'queued' => true]], 202, 'Generation queued.')]
    #[ResponseField('data.batch_id', 'string', 'Batch identifier; list the codes with `GET …/coupons?batch_id=`.', example: '01J0Z8Q4M6T2W9A7C5V3B1N8XK')]
    #[ResponseField('data.count', 'integer', 'Codes created (inline) or requested (queued).', example: 50)]
    #[ResponseField('data.queued', 'boolean', 'True when generation runs in the background (more than 200 codes).', example: false)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `promotions.manage` for this center, the token lacks `center-access:{center}`, or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found in this center.')]
    #[ApiResponse(['message' => 'This promotion is not triggered by a coupon code.', 'errors' => ['promotion' => ['This promotion is not triggered by a coupon code.']]], 422, 'Validation failed, or the promotion is not coupon-triggered.')]
    public function generate(GenerateCouponsRequest $request, Center $center, Promotion $promotion): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        return $this->generateCoupons($request, $promotion);
    }

    #[Endpoint('Update a coupon', 'Partial update of limits, window, status and assignment mode. The code itself cannot change.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('promotion', 'integer', 'Promotion ID (must belong to the center).', example: 12)]
    #[UrlParam('coupon', 'integer', 'Coupon ID (must belong to the promotion).', example: 41)]
    #[ResponseFromApiResource(CouponResource::class, Coupon::class, status: 200, description: 'The updated coupon.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `promotions.manage` for this center, the token lacks `center-access:{center}`, or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found in this center.')]
    #[ApiResponse(['message' => 'The code field is prohibited.', 'errors' => ['code' => ['The code field is prohibited.']]], 422, 'Validation failed (e.g. `code` sent).')]
    public function update(UpdateCouponRequest $request, Center $center, Promotion $promotion, Coupon $coupon): CouponResource
    {
        $this->authorizeCenter($request, $center);

        $coupon->update($request->validated());

        return CouponResource::make($coupon->refresh());
    }

    #[Endpoint('List a promotion\'s redemptions', 'The ledger: one row per purchase, with the discount, who funded it and whether it was released (purchase cancelled or refunded). Newest first, paginated.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('promotion', 'integer', 'Promotion ID (must belong to the center).', example: 12)]
    #[QueryParam('status', 'string', 'Filter by status.', required: false, enum: RedemptionStatus::class, example: 'applied')]
    #[QueryParam('client_id', 'integer', 'Only this client\'s redemptions.', required: false, example: 77)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(RedemptionResource::class, PromotionRedemption::class, status: 200, description: 'Paginated redemptions.', paginate: 15, with: ['coupon'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `promotions.manage` for this center, the token lacks `center-access:{center}`, or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Promotion or coupon not found in this center.')]
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
