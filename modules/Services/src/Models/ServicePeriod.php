<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Database\Factories\ServicePeriodFactory;
use Modules\Services\Enums\PeriodGender;

class ServicePeriod extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'gender',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'gender' => PeriodGender::class,
            'day_of_week' => 'integer',
        ];
    }

    protected static function newFactory(): ServicePeriodFactory
    {
        return ServicePeriodFactory::new();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
