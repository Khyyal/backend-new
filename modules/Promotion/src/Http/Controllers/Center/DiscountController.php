<?php

namespace Modules\Promotion\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User as CenterUser;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Events\DiscountCreated;
use Modules\Promotion\Http\Requests\AttachDiscountablesRequest;
use Modules\Promotion\Http\Requests\StoreCouponRequest;
use Modules\Promotion\Http\Requests\StoreDiscountRequest;
use Modules\Promotion\Http\Requests\UpdateCouponStatusRequest;
use Modules\Promotion\Http\Requests\UpdateDiscountRequest;
use Modules\Promotion\Http\Requests\UpdateDiscountStatusRequest;
use Modules\Promotion\Http\Resources\CouponCollection;
use Modules\Promotion\Http\Resources\CouponResource;
use Modules\Promotion\Http\Resources\DiscountCollection;
use Modules\Promotion\Http\Resources\DiscountResource;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Discount;
use Modules\Promotion\Models\Discountable;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Discounts', description: 'Authenticated center user: manage discounts, coupons, and discountable items for their center.')]
class DiscountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:center_user');
    }

    private function owner(Request $request): Center
    {
        /** @var CenterUser $user */
        $user = $request->user('center_user');

        $center = $user->centers()->first();
        abort_if($center === null, ResponseAlias::HTTP_FORBIDDEN, 'No center assignment found for user.');

        return $center;
    }

    #[Response(status: 200, description: 'Paginated list of center-owned discounts with counts.')]
    #[Response(status: 401, description: 'Missing or invalid center_user Bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $owner = $this->owner($request);
        $query = Discount::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->withCount(['coupons', 'redemptions']);

        $this->applyFilters($query, $request);

        return DiscountCollection::make($query->latest()->paginate(25));
    }

    #[Response(status: 200, description: 'Single discount detail.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 403, description: 'Discount does not belong to user\'s center.')]
    #[Response(status: 404, description: 'Discount not found.')]
    public function show(Request $request, Discount $discount): DiscountResource
    {
        $this->assertOwned($request, $discount);
        $discount->loadCount(['coupons', 'redemptions']);

        return DiscountResource::make($discount);
    }

    #[Response(status: 201, description: 'Discount created for center.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function store(StoreDiscountRequest $request): DiscountResource
    {
        $validated = $request->validated();
        $owner = $this->owner($request);

        $discount = DB::transaction(function () use ($validated, $owner) {
            $discount = Discount::query()->create(array_merge($validated, [
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'is_stackable' => $validated['is_stackable'] ?? true,
                'status' => $validated['status'] ?? PromotionStatus::Active,
            ]));

            DB::afterCommit(fn () => event(new DiscountCreated($discount)));

            return $discount;
        });

        $discount->loadCount(['coupons', 'redemptions']);

        return DiscountResource::make($discount)->response()->setStatusCode(201);
    }

    #[Response(status: 200, description: 'Discount updated.')]
    #[Response(status: 403, description: 'Discount does not belong to user\'s center.')]
    #[Response(status: 404, description: 'Discount not found.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function update(UpdateDiscountRequest $request, Discount $discount): DiscountResource
    {
        $this->assertOwned($request, $discount);
        $discount->update($request->validated());
        $discount->loadCount(['coupons', 'redemptions']);

        return DiscountResource::make($discount);
    }

    #[Response(status: 204, description: 'Discount deleted.')]
    #[Response(status: 403, description: 'Discount does not belong to user\'s center.')]
    #[Response(status: 422, description: 'Discount has redemptions.')]
    public function destroy(Request $request, Discount $discount): JsonResponse
    {
        $this->assertOwned($request, $discount);

        if ($discount->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'discount_id' => [__('promotion::validation.discount_has_redemptions')],
            ]);
        }

        $discount->delete();

        return response()->json(null, 204);
    }

    #[Response(status: 200, description: 'Discount status updated.')]
    #[Response(status: 403, description: 'Discount does not belong to user\'s center.')]
    #[Response(status: 422, description: 'Invalid status value.')]
    public function updateStatus(UpdateDiscountStatusRequest $request, Discount $discount): DiscountResource
    {
        $this->assertOwned($request, $discount);
        $discount->update(['status' => $request->validated()['status']]);
        $discount->loadCount(['coupons', 'redemptions']);

        return DiscountResource::make($discount);
    }

    // ---------- Nested Coupons ----------

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 200, description: 'Paginated coupons for the discount.')]
    public function couponsIndex(Request $request, Discount $discount): AnonymousResourceCollection
    {
        $this->assertOwned($request, $discount);
        $query = $discount->coupons()->withCount('redemptions');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return CouponCollection::make($query->latest()->paginate(50));
    }

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 201, description: 'Coupons created.')]
    #[Response(status: 422, description: 'Missing coupons array or generator config.')]
    public function couponsStore(StoreCouponRequest $request, Discount $discount): CouponCollection
    {
        $this->assertOwned($request, $discount);

        $validated = $request->validated();
        $coupons = DB::transaction(function () use ($discount, $validated) {
            $toCreate = [];

            if (isset($validated['coupons'])) {
                foreach ($validated['coupons'] as $c) {
                    $toCreate[] = [
                        'code' => Str::upper(trim($c['code'])),
                        'starts_at' => $c['starts_at'],
                        'ends_at' => $c['ends_at'] ?? null,
                        'status' => PromotionStatus::Active,
                    ];
                }
            }

            if (isset($validated['generator'])) {
                $g = $validated['generator'];
                $prefix = Str::upper($g['prefix']);
                for ($i = 0; $i < (int) $g['quantity']; $i++) {
                    $toCreate[] = [
                        'code' => $prefix.Str::upper(Str::random((int) $g['length'])),
                        'starts_at' => $g['starts_at'],
                        'ends_at' => $g['ends_at'] ?? null,
                        'status' => PromotionStatus::Active,
                    ];
                }
            }

            $created = collect();
            foreach ($toCreate as $data) {
                $created->push($discount->coupons()->create($data));
            }

            return $created;
        });

        $coupons->loadCount('redemptions');

        return CouponCollection::make($coupons)->response()->setStatusCode(201);
    }

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 204, description: 'Coupon deleted.')]
    #[Response(status: 422, description: 'Coupon has redemptions.')]
    public function couponsDestroy(Request $request, Discount $discount, Coupon $coupon): JsonResponse
    {
        $this->assertOwned($request, $discount);
        abort_if((int) $coupon->discount_id !== (int) $discount->id, 404);

        if ($coupon->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'coupon_id' => [__('promotion::validation.coupon_has_redemptions')],
            ]);
        }

        $coupon->delete();

        return response()->json(null, 204);
    }

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 200, description: 'Coupon status updated.')]
    public function couponsUpdateStatus(UpdateCouponStatusRequest $request, Discount $discount, Coupon $coupon): CouponResource
    {
        $this->assertOwned($request, $discount);
        abort_if((int) $coupon->discount_id !== (int) $discount->id, 404);
        $coupon->update(['status' => $request->validated()['status']]);
        $coupon->loadCount('redemptions');

        return CouponResource::make($coupon);
    }

    // ---------- Nested Discountables ----------

    #[Group(name: 'Center / Discount Items')]
    #[Response(status: 201, description: 'Items attached to discount.')]
    public function discountablesAttach(AttachDiscountablesRequest $request, Discount $discount): JsonResponse
    {
        $this->assertOwned($request, $discount);

        $items = $request->validated()['items'];
        $now = now();
        $upsert = [];
        foreach ($items as $item) {
            $upsert[] = [
                'discount_id' => $discount->id,
                'discountable_type' => $item['discountable_type'],
                'discountable_id' => $item['discountable_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($upsert) > 0) {
            Discountable::query()->upsert(
                $upsert,
                ['discount_id', 'discountable_type', 'discountable_id'],
                ['updated_at' => $now]
            );
        }

        return response()->json([
            'attached' => count($items),
            'discountable_count' => $discount->discountables()->count(),
        ], 201);
    }

    #[Group(name: 'Center / Discount Items')]
    #[Response(status: 204, description: 'Item detached.')]
    #[Response(status: 404, description: 'Item not attached.')]
    public function discountablesDetach(Request $request, Discount $discount, string $discountableType, string $discountableId): JsonResponse
    {
        $this->assertOwned($request, $discount);

        $detached = Discountable::query()
            ->where('discount_id', $discount->id)
            ->where('discountable_type', $discountableType)
            ->where('discountable_id', $discountableId)
            ->delete();

        if ($detached === 0) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    // ---------- helpers ----------

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach (['status', 'type', 'scope', 'application_method'] as $f) {
            if ($v = $request->input($f)) {
                $query->where($f, $v);
            }
        }
    }

    private function assertOwned(Request $request, Discount $discount): void
    {
        $owner = $this->owner($request);
        abort_unless(
            $discount->owner_type === $owner->getMorphClass()
            && (string) $discount->owner_id === (string) $owner->getKey(),
            ResponseAlias::HTTP_FORBIDDEN
        );
    }
}
