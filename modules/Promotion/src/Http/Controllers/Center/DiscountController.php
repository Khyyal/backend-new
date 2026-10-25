<?php

namespace Modules\Promotion\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Models\Center;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Events\DiscountCreated;
use Modules\Promotion\Http\Requests\AttachServicesRequest;
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
use Modules\Services\Models\Service;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * Discounts and coupons belong to a center. Route bindings are scoped, so a
 * discount of another center (or a coupon/service not under the discount)
 * resolves to 404.
 */
#[Group(name: 'Center / Discounts', description: 'Manage the discounts, coupons and discounted services of a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class DiscountController extends Controller
{
    #[Response(status: 200, description: 'Paginated list of the center\'s discounts with counts.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): DiscountCollection
    {
        $this->authorizeCenter($request, $center);

        $query = $center->discounts()->withCount(['coupons', 'redemptions', 'services']);

        $this->applyFilters($query, $request);

        return DiscountCollection::make($query->latest()->paginate(25));
    }

    #[Response(status: 200, description: 'Single discount with counts and attached `service_ids`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'Discount not found in this center.')]
    public function show(Request $request, Center $center, Discount $discount): DiscountResource
    {
        $this->authorizeCenter($request, $center);

        return DiscountResource::make($this->loadDetails($discount));
    }

    #[Response(status: 201, description: 'Discount created for the center.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function store(StoreDiscountRequest $request, Center $center): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        $validated = $request->validated();

        $discount = DB::transaction(function () use ($validated, $center) {
            $discount = $center->discounts()->create(array_merge($validated, [
                'is_stackable' => $validated['is_stackable'] ?? true,
                'status' => $validated['status'] ?? PromotionStatus::Active,
            ]));

            DB::afterCommit(fn () => event(new DiscountCreated($discount)));

            return $discount;
        });

        return DiscountResource::make($this->loadDetails($discount))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Response(status: 200, description: 'Discount updated.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'Discount not found in this center.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function update(UpdateDiscountRequest $request, Center $center, Discount $discount): DiscountResource
    {
        $this->authorizeCenter($request, $center);

        $discount->update($request->validated());

        return DiscountResource::make($this->loadDetails($discount));
    }

    #[Response(status: 204, description: 'Discount deleted.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'Discount not found in this center.')]
    #[Response(status: 422, description: 'Discount has redemptions and cannot be deleted.')]
    public function destroy(Request $request, Center $center, Discount $discount): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        if ($discount->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'discount_id' => [__('promotion::validation.discount_has_redemptions')],
            ]);
        }

        $discount->delete();

        return response()->json(null, ResponseAlias::HTTP_NO_CONTENT);
    }

    #[Response(status: 200, description: 'Discount status updated.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Invalid status value.')]
    public function updateStatus(UpdateDiscountStatusRequest $request, Center $center, Discount $discount): DiscountResource
    {
        $this->authorizeCenter($request, $center);

        $discount->update(['status' => $request->validated()['status']]);

        return DiscountResource::make($this->loadDetails($discount));
    }

    // ---------- Nested Coupons ----------

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 200, description: 'Paginated coupons for the discount.')]
    #[Response(status: 404, description: 'Discount not found in this center.')]
    public function couponsIndex(Request $request, Center $center, Discount $discount): CouponCollection
    {
        $this->authorizeCenter($request, $center);

        $query = $discount->coupons()->withCount('redemptions');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return CouponCollection::make($query->latest()->paginate(50));
    }

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 201, description: 'Coupons created.')]
    #[Response(status: 422, description: 'Missing coupons array or generator config.')]
    public function couponsStore(StoreCouponRequest $request, Center $center, Discount $discount): JsonResponse
    {
        $this->authorizeCenter($request, $center);

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

            $created = new Collection;
            foreach ($toCreate as $data) {
                $created->push($discount->coupons()->create($data));
            }

            return $created;
        });

        $coupons->loadCount('redemptions');

        return CouponCollection::make($coupons)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 204, description: 'Coupon deleted.')]
    #[Response(status: 404, description: 'Coupon not found under this discount.')]
    #[Response(status: 422, description: 'Coupon has redemptions.')]
    public function couponsDestroy(Request $request, Center $center, Discount $discount, Coupon $coupon): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        if ($coupon->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'coupon_id' => [__('promotion::validation.coupon_has_redemptions')],
            ]);
        }

        $coupon->delete();

        return response()->json(null, ResponseAlias::HTTP_NO_CONTENT);
    }

    #[Group(name: 'Center / Discount Coupons')]
    #[Response(status: 200, description: 'Coupon status updated.')]
    #[Response(status: 404, description: 'Coupon not found under this discount.')]
    public function couponsUpdateStatus(UpdateCouponStatusRequest $request, Center $center, Discount $discount, Coupon $coupon): CouponResource
    {
        $this->authorizeCenter($request, $center);

        $coupon->update(['status' => $request->validated()['status']]);
        $coupon->loadCount('redemptions');

        return CouponResource::make($coupon);
    }

    // ---------- Nested Services ----------

    /**
     * Attach services of this center to the discount.
     *
     * **Request body:** `services` required array of service ids belonging to
     * the center. Already attached services are ignored.
     */
    #[Group(name: 'Center / Discount Services')]
    #[Response(status: 200, description: 'The discount with its updated `service_ids`.')]
    #[Response(status: 422, description: 'A service id does not exist or belongs to another center.')]
    public function servicesAttach(AttachServicesRequest $request, Center $center, Discount $discount): DiscountResource
    {
        $this->authorizeCenter($request, $center);

        $discount->services()->syncWithoutDetaching($request->validated()['services']);

        return DiscountResource::make($this->loadDetails($discount));
    }

    #[Group(name: 'Center / Discount Services')]
    #[Response(status: 204, description: 'Service detached.')]
    #[Response(status: 404, description: 'Service is not attached to this discount.')]
    public function servicesDetach(Request $request, Center $center, Discount $discount, Service $service): JsonResponse
    {
        $this->authorizeCenter($request, $center);

        $discount->services()->detach($service);

        return response()->json(null, ResponseAlias::HTTP_NO_CONTENT);
    }

    // ---------- helpers ----------

    private function authorizeCenter(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('manageDiscounts', $center);
    }

    private function loadDetails(Discount $discount): Discount
    {
        return $discount
            ->load('services:id')
            ->loadCount(['coupons', 'redemptions', 'services']);
    }

    private function applyFilters(Builder|HasMany $query, Request $request): void
    {
        foreach (['status', 'type', 'scope', 'application_method'] as $f) {
            if ($v = $request->input($f)) {
                $query->where($f, $v);
            }
        }
    }
}
