<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centers\Database\Factories\HorseFactory;
use Modules\Clients\Models\Client;
use Modules\Services\Models\Trainer;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A horse kept by a center. It is owned by the center itself when
 * `client_id` is null, otherwise by that client.
 */
class Horse extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'room',
        'client_id',
    ];

    protected static function newFactory(): HorseFactory
    {
        return HorseFactory::new();
    }

    protected function casts(): array
    {
        return [
            'room' => 'integer',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Trainers working with this horse (trainers of the same center).
     */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class)->withTimestamps();
    }

    public function isOwnedByCenter(): bool
    {
        return $this->client_id === null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
            ->useFallbackUrl('')
            ->registerMediaConversions(function (?Media $media = null): void {
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 400, 400);
            });
    }
}
