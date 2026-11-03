<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One discount applied to a purchase, snapshotted when it was priced.
 * `purchase_item_id` is null for discounts on the whole purchase.
 */
class PurchaseAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'purchase_id',
        'purchase_item_id',
        'type',
        'promotion_id',
        'coupon_id',
        'code',
        'label',
        'discount_type',
        'discount_value',
        'amount',
        'funded_by',
        'center_share_amount',
    ];

    protected function casts(): array
    {
        return [
            'label' => 'array',
            'discount_value' => 'integer',
            'amount' => 'integer',
            'center_share_amount' => 'integer',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }
}
