<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centers\Models\Center;
use Modules\Purchase\Database\Factories\FeeRuleFactory;
use Modules\Purchase\Enums\PurchaseChannel;

/**
 * The platform fee on a center's purchases: a percentage (basis points) plus
 * a fixed amount, kept within the optional min and max. Without a center it
 * is the platform default; without a channel it is the fallback.
 */
class FeeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'channel',
        'percentage_bps',
        'fixed_amount',
        'min_amount',
        'max_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channel' => PurchaseChannel::class,
            'percentage_bps' => 'integer',
            'fixed_amount' => 'integer',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * @param  Builder<FeeRule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The fee on a purchase whose center earns `$baseAmount`. It never
     * exceeds that amount, and nothing earned means no fee.
     */
    public function calculate(int $baseAmount): int
    {
        if ($baseAmount <= 0) {
            return 0;
        }

        $fee = intdiv($baseAmount * $this->percentage_bps, 10000) + $this->fixed_amount;

        if ($this->min_amount !== null) {
            $fee = max($fee, $this->min_amount);
        }

        if ($this->max_amount !== null) {
            $fee = min($fee, $this->max_amount);
        }

        return min($fee, $baseAmount);
    }

    protected static function newFactory(): FeeRuleFactory
    {
        return FeeRuleFactory::new();
    }
}
