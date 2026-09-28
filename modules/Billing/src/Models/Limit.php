<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Billing\Database\Factories\LimitFactory;

class Limit extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_limits', 'limit_id', 'plan_id')
            ->withPivot('value')
            ->withTimestamps();
    }

    protected static function newFactory(): LimitFactory
    {
        return LimitFactory::new();
    }
}
