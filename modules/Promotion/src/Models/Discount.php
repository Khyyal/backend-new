<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Centers\Models\Center;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Services\Models\Service;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'name',
        'description',
        'type',
        'value',
        'scope',
        'application_method',
        'minimum_amount',
        'maximum_discount',
        'usage_limit',
        'usage_limit_per_customer',
        'is_stackable',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'scope' => DiscountScope::class,
            'application_method' => ApplicationMethod::class,
            'minimum_amount' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'is_stackable' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => PromotionStatus::class,
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Services the discount is restricted to when its scope is `specific_items`.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'discount_service')->withTimestamps();
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    protected static function newFactory(): DiscountFactory
    {
        return DiscountFactory::new();
    }
}
