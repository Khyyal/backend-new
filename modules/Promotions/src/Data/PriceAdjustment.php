<?php

namespace Modules\Promotions\Data;

use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\FundedBy;

/**
 * One discount applied by a promotion. `lineIndex` is null for a discount on the whole purchase.
 */
final readonly class PriceAdjustment
{
    /**
     * @param  array<string, string>  $label  Translations snapshotted from the promotion.
     */
    public function __construct(
        public int $promotionId,
        public array $label,
        public DiscountType $discountType,
        public int $discountValue,
        public int $amount,
        public FundedBy $fundedBy,
        public int $centerShareAmount,
        public ?int $lineIndex = null,
        public ?int $couponId = null,
        public ?string $couponCode = null,
    ) {}

    public function withCoupon(int $couponId, string $couponCode): self
    {
        return new self(
            $this->promotionId,
            $this->label,
            $this->discountType,
            $this->discountValue,
            $this->amount,
            $this->fundedBy,
            $this->centerShareAmount,
            $this->lineIndex,
            $couponId,
            $couponCode,
        );
    }

    /**
     * @return 'promotion'|'coupon'
     */
    public function type(): string
    {
        return $this->couponId !== null ? 'coupon' : 'promotion';
    }

    public function key(): string
    {
        return $this->couponCode !== null ? 'coupon:'.$this->couponCode : 'promo:'.$this->promotionId;
    }

    public function localizedLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->label[$locale]
            ?? $this->label[config('app.fallback_locale', 'en')]
            ?? (string) (array_values($this->label)[0] ?? '');
    }
}
