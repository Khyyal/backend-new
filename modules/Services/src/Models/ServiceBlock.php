<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\BlockTimeScope;

class ServiceBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'service_id',
        'reason',
        'start_date',
        'end_date',
        'time_scope',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'time_scope' => BlockTimeScope::class,
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ServiceBlockPeriod::class);
    }

    public function scopeForService(Builder $query, Service $service): Builder
    {
        return $query->where('center_id', $service->center_id)
            ->where(function (Builder $query) use ($service) {
                $query->whereNull('service_id')->orWhere('service_id', $service->getKey());
            });
    }

    public function scopeActiveOn(Builder $query, string $date, ?string $time = null): Builder
    {
        $query->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);

        return $query->where(function (Builder $query) use ($time) {
            $query->where('time_scope', BlockTimeScope::AllDay);

            if ($time !== null) {
                $query->orWhere(function (Builder $query) use ($time) {
                    $query->where('time_scope', BlockTimeScope::SpecificTime)
                        ->whereHas('periods', function (Builder $periods) use ($time) {
                            $periods->where('start_time', '<=', $time)->where('end_time', '>=', $time);
                        });
                });
            }
        });
    }
}
