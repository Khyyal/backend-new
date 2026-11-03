<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Promotion\Traits\HasDiscountRedemptions;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;

class Purchase extends Model
{
    use HasDiscountRedemptions;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'buyer_type',
        'buyer_id',
        'merchant_type',
        'merchant_id',
        'source',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source' => PurchaseSource::class,
            'status' => PurchaseStatus::class,
            'metadata' => 'array',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function buyer(): MorphTo
    {
        return $this->morphTo();
    }

    public function merchant(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The most recent payment attempt, which reflects where the purchase's payment stands.
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    protected static function newFactory(): PurchaseFactory
    {
        return PurchaseFactory::new();
    }
}
