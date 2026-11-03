<?php

namespace Modules\Promotions\Services;

use Carbon\CarbonInterface;
use Modules\Clients\Models\Client;
use Modules\Promotions\Contracts\PromotionResolver;
use Modules\Promotions\Data\CouponOutcome;
use Modules\Promotions\Data\DiscountableLine;
use Modules\Promotions\Data\DiscountResult;
use Modules\Promotions\Data\PriceAdjustment;
use Modules\Promotions\Enums\CouponCheck;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;

class PromotionResolverService implements PromotionResolver
{
    public function __construct(
        private readonly ActivePromotionRepository $promotions,
        private readonly DiscountEngine $engine,
        private readonly UsageLimitGuard $limits,
    ) {}

    public function resolve(
        int $centerId,
        array $lines,
        ?CarbonInterface $at = null,
        ?Client $client = null,
        ?string $couponCode = null,
    ): DiscountResult {
        $at ??= now();

        $automatic = $this->limits->available($this->promotions->liveFor($centerId, $at), $client?->getKey());
        $code = $couponCode !== null ? Coupon::normalizeCode($couponCode) : '';

        if ($code === '') {
            return $this->engine->apply($lines, $automatic);
        }

        [$coupon, $check] = $this->checkCoupon($code, $centerId, $lines, $at, $client);

        if ($check !== CouponCheck::Applied) {
            return $this->engine->apply($lines, $automatic)->withCoupon(new CouponOutcome($code, $check));
        }

        $result = $this->engine->apply($lines, $automatic->push($coupon->promotion));
        $couponWon = collect($result->adjustments)->contains(fn (PriceAdjustment $adjustment): bool => $adjustment->promotionId === $coupon->promotion_id);

        if (! $couponWon) {
            return $result->withCoupon(new CouponOutcome($code, CouponCheck::CouponNotBetter));
        }

        $adjustments = array_map(
            fn (PriceAdjustment $adjustment): PriceAdjustment => $adjustment->promotionId === $coupon->promotion_id
                ? $adjustment->withCoupon($coupon->getKey(), $coupon->code)
                : $adjustment,
            $result->adjustments,
        );

        return new DiscountResult(
            $result->lineDiscounts,
            $adjustments,
            new CouponOutcome($code, CouponCheck::Applied, $coupon->getKey(), $coupon->promotion_id),
        );
    }

    /**
     * The first failing check decides the outcome. Failures that would reveal
     * whether a code exists all report `invalid`, so codes can't be probed.
     *
     * @param  list<DiscountableLine>  $lines
     * @return array{0: Coupon|null, 1: CouponCheck}
     */
    private function checkCoupon(string $code, int $centerId, array $lines, CarbonInterface $at, ?Client $client): array
    {
        $coupon = Coupon::query()->where('code', $code)->with(['promotion.targets', 'promotion.schedules'])->first();
        $promotion = $coupon?->promotion;

        if ($coupon === null || $promotion === null
            || $coupon->status !== CouponStatus::Active
            || $promotion->trigger !== PromotionTrigger::Coupon
            || $promotion->status !== PromotionStatus::Active) {
            return [null, CouponCheck::Invalid];
        }

        if ($client === null || $client->needs_onboarding) {
            return [null, CouponCheck::NotEligible];
        }

        if ($coupon->is_assigned_only && ! $coupon->assignedClients()->whereKey($client->getKey())->exists()) {
            return [null, CouponCheck::Invalid];
        }

        if ($promotion->center_id !== null && $promotion->center_id !== $centerId) {
            return [null, CouponCheck::NotApplicableToCart];
        }

        if (($coupon->starts_at !== null && $coupon->starts_at > $at) || $promotion->starts_at > $at) {
            return [null, CouponCheck::NotStarted];
        }

        if (($coupon->ends_at !== null && $coupon->ends_at <= $at) || ($promotion->ends_at !== null && $promotion->ends_at <= $at)) {
            return [null, CouponCheck::Expired];
        }

        if (! $promotion->isScheduledAt($at)) {
            return [null, CouponCheck::NotEligible];
        }

        if ($promotion->first_booking_only && $this->limits->hasPriorBookings($client->getKey())) {
            return [null, CouponCheck::NotEligible];
        }

        if (($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) || $this->limits->totalLimitReached($promotion)) {
            return [null, CouponCheck::Exhausted];
        }

        if ($this->clientLimitReached($coupon, $promotion, $client->getKey())) {
            return [null, CouponCheck::ClientLimitReached];
        }

        return match ($this->engine->eligibility($promotion, $lines)) {
            'not_applicable' => [null, CouponCheck::NotApplicableToCart],
            'min_subtotal' => [null, CouponCheck::MinSubtotalNotMet],
            default => [$coupon, CouponCheck::Applied],
        };
    }

    /**
     * A coupon-level per-client limit overrides the promotion-level one.
     */
    private function clientLimitReached(Coupon $coupon, Promotion $promotion, int $clientId): bool
    {
        if ($coupon->per_client_usage_limit !== null) {
            return $this->limits->clientUses('coupon_client_usages', 'coupon_id', $coupon->getKey(), $clientId) >= $coupon->per_client_usage_limit;
        }

        return $promotion->per_client_usage_limit !== null
            && $this->limits->clientUses('promotion_client_usages', 'promotion_id', $promotion->getKey(), $clientId) >= $promotion->per_client_usage_limit;
    }
}
