<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centers\Models\Center;
use Modules\Services\Concerns\HasSchedules;

class Trainer extends Model
{
    use HasFactory, HasSchedules, SoftDeletes;

    protected $fillable = [
        'center_id',
        'name',
        'max_tickets_per_hour',
        'max_tickets_per_hour_by_day',
    ];

    protected function casts(): array
    {
        return [
            'max_tickets_per_hour' => 'integer',
            'max_tickets_per_hour_by_day' => 'array',
        ];
    }

    /**
     * Hourly ticket limit on a weekday (0-6): the day's override when set, else
     * the default `max_tickets_per_hour`; `null` means no limit.
     */
    public function maxTicketsPerHourOn(int $dayOfWeek): ?int
    {
        $override = $this->max_tickets_per_hour_by_day[$dayOfWeek] ?? null;

        return $override === null ? $this->max_tickets_per_hour : (int) $override;
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function trainingServices(): BelongsToMany
    {
        return $this->belongsToMany(TrainingService::class);
    }
}
