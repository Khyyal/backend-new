<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotions\Data\PriceAdjustment;
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
