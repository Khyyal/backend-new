<?php

namespace Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clients\Models\Client;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\RedemptionStatus;
use Modules\Purchase\Models\Purchase;

/**
 * Ledger row: one per (purchase, promotion). Never deleted; released rows keep their history.
 */
class PromotionRedemption extends Model
{
    protected $fillable = [
        'promotion_id',
        'coupon_id',
        'purchase_id',
        'client_id',
        'discount_amount',
        'funded_by',
        'center_share_amount',
        'status',
        'released_at',
        'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RedemptionStatus::class,
            'funded_by' => FundedBy::class,
            'discount_amount' => 'integer',
            'center_share_amount' => 'integer',
            'released_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
