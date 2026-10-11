<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Database\Factories\PriceOptionFactory;
use Modules\Services\Enums\PriceOptionUnit;

class PriceOption extends Model
{
    use HasFactory , HasService , SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'price',
        'quantity',
        'unit',
        'service_id',
    ];

    protected static function newFactory(): PriceOptionFactory
    {
        return PriceOptionFactory::new();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function package(): HasOne
    {
        return $this->hasOne(ServicePackage::class);
    }

    protected $casts = [
        'unit' => PriceOptionUnit::class,
    ];
}
