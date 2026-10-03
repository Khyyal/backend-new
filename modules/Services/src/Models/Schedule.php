<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedulable_type',
        'schedulable_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public $timestamps = false;


    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForDay(
        Builder $query,
        int $dayOfWeek,
    ): Builder {
        return $query
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time');
    }




}
