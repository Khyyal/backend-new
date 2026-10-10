<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Concerns\HasStartPrice;

class TrainingService extends Model
{
    use HasFactory, HasService, HasStartPrice, SoftDeletes;

    public $timestamps = false;

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class);
    }
}
