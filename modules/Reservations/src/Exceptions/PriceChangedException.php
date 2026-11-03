<?php

namespace Modules\Reservations\Exceptions;

use Illuminate\Http\JsonResponse;
use Modules\Reservations\Booking\CheckoutQuote;
use Modules\Reservations\Http\Resources\CheckoutQuoteResource;
use RuntimeException;

/**
 * The checkout total no longer matches what the buyer was quoted (an offer
 * started, ended or ran out). Nothing was booked; the buyer should confirm
 * the new total.
 */
class PriceChangedException extends RuntimeException
{
    public function __construct(public readonly ?CheckoutQuote $quote = null)
    {
        parent::__construct(__('promotions::messages.price_changed'));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'price_changed',
            'data' => $this->quote !== null ? CheckoutQuoteResource::make($this->quote)->resolve() : null,
        ], 409);
    }
}
