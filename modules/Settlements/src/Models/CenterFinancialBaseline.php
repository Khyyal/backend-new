<?php

namespace Modules\Settlements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centers\Models\Center;
use Modules\Settlements\Database\Factories\CenterFinancialBaselineFactory;
use Modules\Support\Models\SystemUser;

/**
 * The true financial figures of a migrated center as of `cutoff_at`
 * (`target_*`, set by finance) next to what the migrated data said
 * (`migrated_*`, frozen at the first confirmation). Until `confirmed_at` is
 * set the center gets no payouts. Change it only through
 * SetCenterFinancialBaseline.
 */
class CenterFinancialBaseline extends Model
{
    use HasFactory;

    /**
     * Figures that have both a target and a migrated value, besides the balance.
     */
    public const STAT_FIGURES = ['platform_fees', 'gross_sales', 'refunds'];

    protected $fillable = [
        'center_id',
        'cutoff_at',
        'target_balance',
        'target_platform_fees',
        'target_paid_out',
        'target_gross_sales',
        'target_refunds',
        'migrated_balance',
        'migrated_platform_fees',
        'migrated_gross_sales',
        'migrated_refunds',
        'note',
        'confirmed_at',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_at' => 'datetime',
            'target_balance' => 'integer',
            'target_platform_fees' => 'integer',
            'target_paid_out' => 'integer',
            'target_gross_sales' => 'integer',
            'target_refunds' => 'integer',
            'migrated_balance' => 'integer',
            'migrated_platform_fees' => 'integer',
            'migrated_gross_sales' => 'integer',
            'migrated_refunds' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * How far the true figure is from the migrated one, or null when finance
     * gave no target for it.
     *
     * @param  'balance'|'platform_fees'|'gross_sales'|'refunds'  $figure
     */
    public function delta(string $figure): ?int
    {
        $target = $this->getAttribute("target_{$figure}");

        return $target === null ? null : $target - (int) $this->getAttribute("migrated_{$figure}");
    }

    protected static function newFactory(): CenterFinancialBaselineFactory
    {
        return CenterFinancialBaselineFactory::new();
    }
}
