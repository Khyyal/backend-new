<?php

namespace Modules\Promotions\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An automatic promotion priced into a purchase stopped being usable before
 * the purchase committed (paused, expired or used up). The client should re-quote.
 */
class PromotionUnavailableException extends RuntimeException
{
    public static function forPromotion(int $promotionId): self
    {
        return new self("Promotion [{$promotionId}] is no longer available.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __('promotions::messages.price_changed'),
            'code' => 'price_changed',
            'data' => null,
        ], 409);
    }
}
