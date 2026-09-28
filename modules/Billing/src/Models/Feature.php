<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Billing\Database\Factories\FeatureFactory;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'is_quota',
    ];

    protected function casts(): array
    {
        return [
            'is_quota' => 'boolean',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features', 'feature_id', 'plan_id')
            ->withPivot('enabled')
            ->withTimestamps();
    }

    protected static function newFactory(): FeatureFactory
    {
        return FeatureFactory::new();
    }
}
