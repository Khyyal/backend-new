<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Concerns\HasSettings;
use Modules\Services\Concerns\HasStartPrice;

class TrainingService extends Model
{
    use HasFactory, HasService, HasSettings, HasStartPrice, SoftDeletes;

    public $timestamps = false;

    const array SETTINGS_KEYS = [
        'enable_cash_on_arrival',
        'enable_single_session_booking',
        'enable_package_booking',
    ];

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class);
    }
}
