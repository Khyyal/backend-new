<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBlockPeriod extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'service_block_id',
        'start_time',
        'end_time',
    ];

    public function serviceBlock(): BelongsTo
    {
        return $this->belongsTo(ServiceBlock::class);
    }
}
