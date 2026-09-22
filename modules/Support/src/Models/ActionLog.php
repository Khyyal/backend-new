<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        "actionable_id",
        "actionable_type",
        "actor_id",
        "actor_type",
    ];


    /// relations
    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
