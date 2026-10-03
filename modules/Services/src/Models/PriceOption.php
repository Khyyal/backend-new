<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Enums\PriceOptionUnit;

class PriceOption extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'price',
        'quantity',
        'unit',
        'service_id',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected $casts = [
        'unit' => PriceOptionUnit::class,
    ];
}
