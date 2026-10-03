<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Centers\Models\Center;
use Modules\Support\Enums\ActivationStatus;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Translatable('name', 'description')]
class Service extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia;


    protected $fillable = [
        'name',
        'center_id',
        'description',
        'status',
        "slug",
        'type',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ActivationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            $service->slug = $service->generateSlug();
        });
    }


    public function generateSlug(): string
    {
        $name = $this->getTranslation('name', app()->getLocale());

        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (
        static::query()
            ->where('center_id', $this->center_id)
            ->where('slug', $slug)
            ->when($this->exists, fn($query) => $query->whereKeyNot($this->getKey()))
            ->exists()
        ) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function registerMediaCollections(): void
    {


        $this->addMediaCollection('cover')
            ->singleFile()

            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg'])
            ->useFallbackUrl('')
            ->registerMediaConversions(function (?Media $media = null): void {
                $this->addMediaConversion('cover-thumb')
                    ->fit(Fit::Crop, 1200, 400);
            });


        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg'])
            ->registerMediaConversions(function (?Media $media = null): void {
                $this->addMediaConversion('gallery-thumb')
                    ->fit(Fit::Crop, 600, 600);
            });
    }


    /// price options
    public function priceOptions(): HasMany|Service
    {
        return $this->hasMany(PriceOption::class);
    }





}
