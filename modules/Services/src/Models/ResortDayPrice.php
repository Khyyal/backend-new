<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResortDayPrice extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'resort_id',
        'day_of_week',
        'price',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function resort(): BelongsTo
    {
        return $this->belongsTo(Resort::class);
    }
}
