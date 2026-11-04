<?php

namespace Modules\Homepage\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Homepage\Database\Factories\HomeSectionFactory;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * One block of the client home page. `type` decides what fills it and which `config` keys apply.
 */
#[Translatable('title', 'subtitle')]
class HomeSection extends Model
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = [
        'key',
        'type',
        'title',
        'subtitle',
        'sort_order',
        'status',
        'starts_at',
        'ends_at',
        'config',
        'created_by',
    ];

    protected static function newFactory(): HomeSectionFactory
    {
        return HomeSectionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => HomeSectionType::class,
            'status' => PublishStatus::class,
            'sort_order' => 'integer',
            'config' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return HasMany<HomeSectionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Banner, $this> */
    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<HomeSection>  $query
     * @return Builder<HomeSection>
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

    /** How many items the home page shows before "see all". */
    public function limit(): int
    {
        return max(1, min(50, (int) ($this->config['limit'] ?? 10)));
    }

    public function radiusKm(): ?float
    {
        $radius = $this->config['radius_km'] ?? null;

        return $radius !== null && $this->type->usesRadius() ? (float) $radius : null;
    }
}
