<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Promotion\Database\Factories\DiscountRedemptionFactory;

class DiscountRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_id',
        'coupon_id',
        'used_by_type',
        'used_by_id',
        'discountable_type',
        'discountable_id',
        'discount_amount',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'redeemed_at' => 'datetime',
        ];
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function usedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function discountable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): DiscountRedemptionFactory
    {
        return DiscountRedemptionFactory::new();
    }
}
