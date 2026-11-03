<?php

namespace Modules\Promotions\Data;

use Modules\Promotions\Enums\CouponCheck;

/**
 * What happened to the coupon code a client entered. Problems never fail a quote; they are reported here.
 */
final readonly class CouponOutcome
{
    public function __construct(
        public string $code,
        public CouponCheck $check,
        public ?int $couponId = null,
        public ?int $promotionId = null,
    ) {}

    public function isApplied(): bool
    {
        return $this->check === CouponCheck::Applied;
    }

    public function message(): string
    {
        return __('promotions::messages.coupon.'.$this->check->value);
    }
}
