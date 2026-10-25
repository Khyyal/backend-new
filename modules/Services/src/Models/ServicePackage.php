<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Database\Factories\ServicePackageFactory;

/**
 * A bundle of `count` sessions for `price`, valid for `duration` months, sold
 * on one price option of a service (at most one package per option).
 */
class ServicePackage extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'price_option_id',
        'count',
        'price',
        'duration',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'price' => 'float',
            'duration' => 'integer',
        ];
    }

    protected static function newFactory(): ServicePackageFactory
    {
        return ServicePackageFactory::new();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function priceOption(): BelongsTo
    {
        return $this->belongsTo(PriceOption::class);
    }
}
