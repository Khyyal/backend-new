<?php

namespace Modules\Promotions\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Promotions\Database\Factories\CampaignFactory;
use Modules\Promotions\Enums\CampaignStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A marketing grouping of platform promotions.
 */
#[Translatable('name', 'description')]
class Campaign extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'status',
        'created_by',
    ];

    protected static function newFactory(): CampaignFactory
    {
        return CampaignFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
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

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    /**
     * Active campaigns inside their (optional) date window.
     */
    public function scopeRunningAt(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->where('status', CampaignStatus::Active->value)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function isRunningAt(CarbonInterface $at): bool
    {
        return $this->status === CampaignStatus::Active
            && ($this->starts_at === null || $this->starts_at <= $at)
            && ($this->ends_at === null || $this->ends_at > $at);
    }
}
