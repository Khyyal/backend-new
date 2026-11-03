<?php

namespace Modules\Promotions\Exceptions;

use Illuminate\Http\JsonResponse;
use Modules\Promotions\Enums\CouponCheck;
use RuntimeException;

class CouponRejectedException extends RuntimeException
{
    public function __construct(public readonly CouponCheck $check)
    {
        parent::__construct("Coupon rejected: {$check->value}");
    }

    public function userMessage(): string
    {
        return __('promotions::messages.coupon.'.$this->check->value);
    }

    /**
     * Rendered like a validation error on `coupon_code`, with the check for
     * clients that branch on it.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->userMessage(),
            'code' => 'coupon_rejected',
            'errors' => ['coupon_code' => [$this->userMessage()]],
            'coupon' => ['status' => $this->check->value, 'message' => $this->userMessage()],
        ], 422);
    }
}
