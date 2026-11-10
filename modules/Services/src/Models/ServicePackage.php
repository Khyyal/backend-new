<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Database\Factories\ServicePackageFactory;
use Modules\Support\Enums\ActivationStatus;

/**
 * A bundle of `count` sessions for `price`, valid for `duration` months, sold
 * on one price option of a service (at most one package per option). Only
 * ACTIVE packages are shown to clients and can be bought.
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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'price' => 'integer',
            'duration' => 'integer',
            'status' => ActivationStatus::class,
        ];
    }

    protected static function newFactory(): ServicePackageFactory
    {
        return ServicePackageFactory::new();
    }

    /**
     * Whether clients can see and buy the package: active and not deleted.
     */
    public function isSellable(): bool
    {
        return $this->status === ActivationStatus::ACTIVE && ! $this->trashed();
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
