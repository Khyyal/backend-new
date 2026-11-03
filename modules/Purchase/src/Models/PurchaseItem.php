<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Promotion\Traits\HasDiscountRedemptions;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;

class PurchaseItem extends Model
{
    use HasDiscountRedemptions;
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'purchasable_type',
        'purchasable_id',
        'name',
        'quantity',
        'unit_price',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): PurchaseItemFactory
    {
        return PurchaseItemFactory::new();
    }
}
