<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Holds a media item between upload and attachment. Deleting this model
 * deletes its media (and files) through Media Library.
 */
class TemporaryUpload extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['uploader_id', 'uploader_type', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOwnedBy(Model $owner): bool
    {
        return $this->uploader_type === $owner->getMorphClass()
            && (string) $this->uploader_id === (string) $owner->getKey();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
