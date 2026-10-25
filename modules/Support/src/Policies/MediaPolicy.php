<?php

namespace Modules\Support\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Modules\Support\Models\TemporaryUpload;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Only the uploader may act on a still-temporary media. Once attached, media
 * belongs to the consuming layer, which exposes its own operations.
 */
class MediaPolicy
{
    public function delete(Authenticatable $user, Media $media): bool
    {
        $upload = $media->model;

        return $user instanceof Model
            && $upload instanceof TemporaryUpload
            && $upload->isOwnedBy($user);
    }
}
