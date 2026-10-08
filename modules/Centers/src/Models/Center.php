<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Billing\Traits\HasSubscriptions;
use Modules\Centers\Enums\CenterStatus;
use Modules\Promotion\Traits\HasDiscounts;
use Modules\Purchase\Contracts\Buyer;
use Modules\Purchase\Traits\IsBuyer;
use Modules\Services\Models\Service;
use Modules\Support\Concerns\Actionable;
use Modules\Support\Concerns\HasCity;
use Modules\Support\Concerns\Rateable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Center extends Model implements Buyer, HasMedia
{
    use Actionable;
    use HasCity;
    use HasDiscounts;
    use HasFactory;
    use HasSubscriptions;
    use InteractsWithMedia;
    use IsBuyer;
    use Rateable;
    use SoftDeletes;

    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'description',
        'lat',
        'lng',
        'address',
        'points',
        'status',
        'contact_phone',
    ];

    public const MEDIA_COLLECTIONS = [
        'logo',
        'cover',
        'images',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'points' => 'integer',
            'status' => CenterStatus::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
            ->useFallbackUrl('')
            ->registerMediaConversions(function (?Media $media = null): void {
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 200, 200);

            });

        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
            ->useFallbackUrl('')
            ->registerMediaConversions(function (?Media $media = null): void {
                $this->addMediaConversion('cover-thumb')
                    ->fit(Fit::Crop, 1200, 400);

            });

        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
            ->registerMediaConversions(function (?Media $media = null): void {
                $this->addMediaConversion('gallery-thumb')
                    ->fit(Fit::Crop, 600, 600);
            });

    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_center');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'center_user_assignment',
            'center_id',
            'user_id'
        )
            ->using(CenterUser::class)
            ->withPivot([
                'status',
                'joined_at',
                'is_primary',
            ])
            ->withTimestamps();
    }

    public function primaryUser(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            CenterUser::class,
            'center_id',
            'id',
            'id',
            'user_id'
        )->where('center_user_assignment.is_primary', true);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    // / scope list (status = visible)
    public function scopeVisible($query)
    {
        return $query->where('status', CenterStatus::VISIBLE);
    }
}
