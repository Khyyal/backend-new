<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Promotions\Data\PriceAdjustment;
use Modules\Promotions\Enums\CouponCheck;
use Modules\Reservations\Booking\CheckoutQuote;

/**
 * A priced checkout, in minor units (halalas). `adjustments` lists each
 * applied discount (`line_index` null for one on the whole purchase);
 * `coupon` reports what happened to the code that was sent, if any.
 *
 * @property CheckoutQuote $resource
 */
class CheckoutQuoteResource extends JsonResource
{
    #[ResponseField('subtotal_amount', 'integer', 'Before discounts, in halalas.', example: 30000)]
    #[ResponseField('discount_amount', 'integer', 'Total discount in halalas.', example: 4500)]
    #[ResponseField('total_amount', 'integer', 'To pay, in halalas. Send it as `expected_total_amount` when booking.', example: 25500)]
    #[ResponseField('lines', 'object[]', 'Per priced line.')]
    #[ResponseField('lines[].index', 'integer', '0-based line index.', example: 0)]
    #[ResponseField('lines[].subtotal_amount', 'integer', 'Line subtotal in halalas.', example: 30000)]
    #[ResponseField('lines[].discount_amount', 'integer', 'Line discount in halalas.', example: 4500)]
    #[ResponseField('lines[].total_amount', 'integer', 'Line total in halalas.', example: 25500)]
    #[ResponseField('adjustments', 'object[]', 'Applied promotions and coupon.')]
    #[ResponseField('adjustments[].key', 'string', 'Stable adjustment key.', example: 'promotion:12:0')]
    #[ResponseField('adjustments[].type', 'string', '`promotion` or `coupon`.', enum: ['promotion', 'coupon'], example: 'promotion')]
    #[ResponseField('adjustments[].promotion_id', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseField('adjustments[].label', 'string', 'Label in the request locale.', example: '15% off rides')]
    #[ResponseField('adjustments[].amount', 'integer', 'Discount in halalas.', example: 4500)]
    #[ResponseField('adjustments[].line_index', 'integer', 'Discounted line; null for order-level discounts.', nullable: true, example: 0)]
    #[ResponseField('coupon', 'object', 'Result of `coupon_code`; null when none was sent.', nullable: true)]
    #[ResponseField('coupon.code', 'string', 'The code.', example: 'SUMMER25')]
    #[ResponseField('coupon.status', 'string', 'Check result.', enum: CouponCheck::class, example: 'applied')]
    #[ResponseField('coupon.message', 'string', 'Localized message.', example: 'Coupon applied.')]
    public function toArray(?Request $request = null): array
    {
        $quote = $this->resource;
        $coupon = $quote->coupon();

        return [
            'subtotal_amount' => $quote->subtotal(),
            'discount_amount' => $quote->discountAmount(),
            'total_amount' => $quote->totalAmount(),
            'lines' => array_map(fn (int $index, int $subtotal): array => [
                'index' => $index,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $quote->lineDiscount($index),
                'total_amount' => $subtotal - $quote->lineDiscount($index),
            ], array_keys($quote->lineSubtotals), $quote->lineSubtotals),
            'adjustments' => array_map(fn (PriceAdjustment $adjustment): array => [
                'key' => $adjustment->key(),
                'type' => $adjustment->type(),
                'promotion_id' => $adjustment->promotionId,
                'label' => $adjustment->localizedLabel(),
                'amount' => $adjustment->amount,
                'line_index' => $adjustment->lineIndex,
            ], $quote->adjustments()),
            'coupon' => $coupon === null ? null : [
                'code' => $coupon->code,
                'status' => $coupon->check->value,
                'message' => $coupon->message(),
            ],
        ];
    }
}
