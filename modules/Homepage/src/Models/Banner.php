<?php

namespace Modules\Homepage\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centers\Models\Center;
use Modules\Homepage\Database\Factories\BannerFactory;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Enums\PublishStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A slide of a banner carousel section. Each locale has its own artwork (`image_en`, `image_ar`);
 * a banner tied to a center is only shown near that center when the section has a radius.
 */
#[Translatable('title', 'subtitle', 'cta_label')]
class Banner extends Model implements HasMedia
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, HasTranslations, InteractsWithMedia;

    public const LOCALES = ['en', 'ar'];

    protected $fillable = [
        'home_section_id',
        'title',
        'subtitle',
        'cta_label',
        'action_type',
        'action_target_id',
        'action_url',
        'center_id',
        'sort_order',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected static function newFactory(): BannerFactory
    {
        return BannerFactory::new();
    }

    protected function casts(): array
    {
        return [
            'action_type' => BannerActionType::class,
            'status' => PublishStatus::class,
            'action_target_id' => 'integer',
            'center_id' => 'integer',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public static function imageCollection(string $locale): string
    {
        return 'image_'.$locale;
    }

    public function registerMediaCollections(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->addMediaCollection(self::imageCollection($locale))
                ->singleFile()
                ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
                ->registerMediaConversions(function (?Media $media = null): void {
                    $this->addMediaConversion('banner-wide')->width(1200)->height(500)->nonQueued();
                    $this->addMediaConversion('banner-thumb')->width(400)->height(167)->nonQueued();
                });
        }
    }

    /** @return BelongsTo<HomeSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * @param  Builder<Banner>  $query
     * @return Builder<Banner>
     */
    public function scopeLiveAt(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->where('status', PublishStatus::Published->value)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function isLiveAt(CarbonInterface $at): bool
    {
        return $this->status === PublishStatus::Published
            && ($this->starts_at === null || $this->starts_at <= $at)
            && ($this->ends_at === null || $this->ends_at > $at);
    }

    /**
     * The artwork for a locale, falling back to any other locale that has one.
     */
    public function imageFor(string $locale): ?Media
    {
        $locales = [$locale, ...array_diff(self::LOCALES, [$locale])];

        foreach ($locales as $candidate) {
            $media = $this->getFirstMedia(self::imageCollection($candidate));

            if ($media !== null) {
                return $media;
            }
        }

        return null;
    }
}
