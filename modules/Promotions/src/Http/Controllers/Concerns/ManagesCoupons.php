<?php

namespace Modules\Promotions\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Promotions\Http\Requests\GenerateCouponsRequest;
use Modules\Promotions\Http\Requests\StoreCouponRequest;
use Modules\Promotions\Http\Resources\CouponResource;
use Modules\Promotions\Http\Resources\RedemptionResource;
use Modules\Promotions\Jobs\GenerateCouponsJob;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\CouponGenerator;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * Coupon endpoints shared by the admin and center controllers; callers authorize first.
 */
trait ManagesCoupons
{
    /** Batches up to this size are generated within the request; larger ones are queued. */
    private const INLINE_GENERATION_LIMIT = 200;

    protected function listCoupons(Request $request, Promotion $promotion): AnonymousResourceCollection
    {
        $coupons = $promotion->coupons()
            ->withCount('assignedClients')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('code'), fn ($query, $code) => $query->where('code', strtoupper(trim($code))))
            ->when($request->query('batch_id'), fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return CouponResource::collection($coupons);
    }

    protected function createCoupon(StoreCouponRequest $request, Promotion $promotion): JsonResponse
    {
        $coupon = $promotion->coupons()->create(array_merge($request->validated(), [
            'creator_type' => $request->user()->getMorphClass(),
            'creator_id' => $request->user()->getKey(),
        ]));

        return CouponResource::make($coupon->refresh())->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    protected function generateCoupons(GenerateCouponsRequest $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validated();
        $count = (int) $validated['count'];
        $prefix = (string) ($validated['prefix'] ?? '');
        $length = (int) ($validated['length'] ?? 8);
        $options = array_diff_key($validated, array_flip(['count', 'prefix', 'length']));
        $generator = app(CouponGenerator::class);
        $batchId = $generator->newBatchId();

        if ($count > self::INLINE_GENERATION_LIMIT) {
            GenerateCouponsJob::dispatch($promotion, $count, $prefix, $length, $batchId, $options, $request->user());

            return response()->json(['data' => ['batch_id' => $batchId, 'count' => $count, 'queued' => true]], ResponseAlias::HTTP_ACCEPTED);
        }

        $created = $generator->generate($promotion, $count, $prefix, $length, $batchId, $options, $request->user());

        return response()->json(['data' => ['batch_id' => $batchId, 'count' => $created, 'queued' => false]], ResponseAlias::HTTP_CREATED);
    }

    protected function listRedemptions(Request $request, Promotion $promotion): AnonymousResourceCollection
    {
        $redemptions = $promotion->redemptions()
            ->with('coupon')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return RedemptionResource::collection($redemptions);
    }
}
