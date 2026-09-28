<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_interval',
        'display_features',
        'status',
        'trial_days',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'price' => 'decimal:2',
            'billing_interval' => BillingInterval::class,
            'display_features' => 'array',
            'status' => PlanStatus::class,
            'trial_days' => 'integer',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features', 'plan_id', 'feature_id')
            ->withPivot('enabled')
            ->withTimestamps();
    }

    public function limits(): BelongsToMany
    {
        return $this->belongsToMany(Limit::class, 'plan_limits', 'plan_id', 'limit_id')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_id');
    }

    public function planLimits(): HasMany
    {
        return $this->hasMany(PlanLimit::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
