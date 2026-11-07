<?php

namespace Modules\Settlements\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Centers\Models\Center;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Settlements\Database\Factories\CenterLedgerEntryFactory;
use Modules\Settlements\Enums\CenterLedgerEntryType;

/**
 * One movement of a center's balance: positive when the platform owes the
 * center, negative when the center owes the platform. It can be paid out
 * once `available_at` has passed.
 */
class CenterLedgerEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'center_id',
        'purchase_id',
        'payment_id',
        'type',
        'amount',
        'available_at',
        'payout_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => CenterLedgerEntryType::class,
            'amount' => 'integer',
            'available_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CenterPayout::class, 'payout_id');
    }

    /**
     * Entries not yet paid out that can be paid out at `$cutoff`.
     *
     * @param  Builder<CenterLedgerEntry>  $query
     */
    public function scopePayableAt(Builder $query, Carbon $cutoff): void
    {
        $query->whereNull('payout_id')->where('available_at', '<=', $cutoff);
    }

    protected static function newFactory(): CenterLedgerEntryFactory
    {
        return CenterLedgerEntryFactory::new();
    }
}
