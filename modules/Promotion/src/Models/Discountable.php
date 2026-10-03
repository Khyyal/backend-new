<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Discountable extends MorphPivot
{
    use HasFactory;

    protected $table = 'discountables';

    public $timestamps = true;

    protected $fillable = [
        'discount_id',
        'discountable_type',
        'discountable_id',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function discountable(): MorphTo
    {
        return $this->morphTo();
    }
}
