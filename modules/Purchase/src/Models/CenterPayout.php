<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Centers\Models\Center;
use Modules\Purchase\Database\Factories\CenterPayoutFactory;
use Modules\Purchase\Enums\CenterPayoutStatus;
use Modules\Support\Models\SystemUser;

/**
 * A transfer of what the platform owes a center, made of its ledger entries
 * available up to `period_end`. Change its status only through
 * CenterPayoutStateService.
 */
class CenterPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'period_end',
        'amount',
        'entries_count',
        'status',
        'iban',
        'account_name',
        'bank_name',
        'reference',
        'paid_at',
        'paid_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CenterPayoutStatus::class,
            'period_end' => 'datetime',
            'amount' => 'integer',
            'entries_count' => 'integer',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CenterLedgerEntry::class, 'payout_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'paid_by');
    }

    protected static function newFactory(): CenterPayoutFactory
    {
        return CenterPayoutFactory::new();
    }
}
