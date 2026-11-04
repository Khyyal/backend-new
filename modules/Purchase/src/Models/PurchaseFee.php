<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Purchase\Enums\PurchaseChannel;

/**
 * The platform fee charged on a purchase, snapshotted with the rule that
 * priced it. A reversed fee is no longer owed.
 */
class PurchaseFee extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'purchase_id',
        'fee_rule_id',
        'channel',
        'base_amount',
        'percentage_bps',
        'fixed_amount',
        'min_amount',
        'max_amount',
        'amount',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => PurchaseChannel::class,
            'base_amount' => 'integer',
            'percentage_bps' => 'integer',
            'fixed_amount' => 'integer',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'amount' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * What the center owes for the purchase.
     */
    public function owedAmount(): int
    {
        return $this->reversed_at === null ? $this->amount : 0;
    }
}
