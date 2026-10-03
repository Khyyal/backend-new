<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Enums\PromotionStatus;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'owner_id',
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

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function discountables(): HasMany
    {
        return $this->hasMany(Discountable::class, 'discount_id');
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
