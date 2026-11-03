<?php

namespace Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Clients\Models\Client;
use Modules\Promotions\Database\Factories\CouponFactory;
use Modules\Promotions\Enums\CouponStatus;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'code',
        'usage_limit',
        'per_client_usage_limit',
        'used_count',
        'starts_at',
        'ends_at',
        'status',
        'is_assigned_only',
        'batch_id',
        'creator_type',
        'creator_id',
    ];

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => CouponStatus::class,
            'usage_limit' => 'integer',
            'per_client_usage_limit' => 'integer',
            'used_count' => 'integer',
            'is_assigned_only' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Codes are stored upper-case and compared without surrounding whitespace.
     */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = self::normalizeCode($value);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'coupon_assignments')->withPivot('assigned_at');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }
}
