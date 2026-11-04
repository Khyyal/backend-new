<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'amount',
        'refunded_amount',
        'provider_fee_amount',
        'method',
        'status',
        'payment_company',
        'payment_type',
        'payment_order_id',
        'provider_data',
        'metadata',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'provider_data' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'provider_fee_amount' => 'integer',
        ];
    }

    /**
     * What is left to refund on a succeeded payment.
     */
    public function refundableAmount(): int
    {
        return $this->amount - $this->refunded_amount;
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
