<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PlanLimit extends Pivot
{
    public $incrementing = true;

    public $timestamps = true;

    protected $table = 'plan_limits';

    protected $fillable = [
        'plan_id',
        'limit_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function limit(): BelongsTo
    {
        return $this->belongsTo(Limit::class);
    }
}
