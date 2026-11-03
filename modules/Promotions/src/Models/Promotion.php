<?php

namespace Modules\Promotions\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centers\Models\Center;
use Modules\Promotions\Data\DiscountableLine;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Enums\PromotionStacking;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Enums\TargetMode;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A discount rule. Money is in minor units (halalas); a percentage
 * `discount_value` is in basis points (1250 = 12.5%). A null `center_id`
 * makes the promotion platform-wide.
 */
#[Translatable('name', 'description', 'badge_label')]
class Promotion extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'center_id',
        'name',
        'description',
        'badge_label',
        'trigger',
        'scope',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_subtotal_amount',
        'starts_at',
        'ends_at',
        'status',
        'priority',
        'stacking',
        'funded_by',
        'center_share_bps',
        'total_usage_limit',
        'per_client_usage_limit',
        'first_booking_only',
        'creator_type',
        'creator_id',
    ];

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'trigger' => PromotionTrigger::class,
            'scope' => PromotionScope::class,
            'discount_type' => DiscountType::class,
            'status' => PromotionStatus::class,
            'stacking' => PromotionStacking::class,
            'funded_by' => FundedBy::class,
            'discount_value' => 'integer',
            'max_discount_amount' => 'integer',
            'min_subtotal_amount' => 'integer',
            'priority' => 'integer',
            'center_share_bps' => 'integer',
            'total_usage_limit' => 'integer',
            'per_client_usage_limit' => 'integer',
            'usage_count' => 'integer',
            'first_booking_only' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (?Media $media = null): void {
                $this->addMediaConversion('cover-thumb')
                    ->width(1200)
                    ->height(400)
                    ->nonQueued();
            });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PromotionSchedule::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    /**
     * Promotions that are switched on and whose date window contains the given moment.
     */
    public function scopeActiveAt(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->where('status', PromotionStatus::Active->value)
            ->where('starts_at', '<=', $at)
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function isActiveAt(CarbonInterface $at): bool
    {
        return $this->status === PromotionStatus::Active
            && $this->starts_at <= $at
            && ($this->ends_at === null || $this->ends_at > $at);
    }

    /**
     * Whether the weekly schedule (if any) allows the promotion at the given
     * moment. Schedules are evaluated in the application timezone.
     */
    public function isScheduledAt(CarbonInterface $at): bool
    {
        if ($this->schedules->isEmpty()) {
            return true;
        }

        $local = $at->copy()->setTimezone(config('app.timezone'));

        return $this->schedules->contains(fn (PromotionSchedule $schedule): bool => $schedule->covers($local));
    }

    /**
     * Exclude targets always remove a line; include targets narrow the lines.
     * With no include targets the promotion applies to every line.
     */
    public function appliesToLine(DiscountableLine $line): bool
    {
        if ($this->center_id !== null && $this->center_id !== $line->centerId) {
            return false;
        }

        $exclusions = $this->targets->filter(fn (PromotionTarget $target): bool => $target->mode === TargetMode::Exclude);

        if ($exclusions->contains(fn (PromotionTarget $target): bool => $target->matches($line))) {
            return false;
        }

        $inclusions = $this->targets->filter(fn (PromotionTarget $target): bool => $target->mode === TargetMode::Include);

        return $inclusions->isEmpty()
            || $inclusions->contains(fn (PromotionTarget $target): bool => $target->matches($line));
    }
}
