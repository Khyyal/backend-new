<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centers\Models\Center;
use Modules\Services\Concerns\HasSchedules;

class Trainer extends Model
{
    use HasFactory, HasSchedules, SoftDeletes;

    protected $fillable = [
        'center_id',
        'name',
        'max_tickets_per_hour',
    ];

    protected function casts(): array
    {
        return [
            'max_tickets_per_hour' => 'integer',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function trainingServices(): BelongsToMany
    {
        return $this->belongsToMany(TrainingService::class);
    }
}
