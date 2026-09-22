<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Rating extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'stars',
        'comment',
        'rateable_id',
        'rateable_type',
        'rater_id',
        'rater_type',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }

    public function rater(): MorphTo
    {
        return $this->morphTo();
    }
}
