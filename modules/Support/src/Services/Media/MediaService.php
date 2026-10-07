<?php

namespace Modules\Support\Services\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Generic operations on permanent media. Knows nothing about concrete models
 * or collection names; both are supplied by the caller.
 */
class MediaService
{
    /**
     * Store an uploaded file directly on a model.
     */
    public function upload(HasMedia&Model $model, UploadedFile $file, string $collection): Media
    {
        return $model->addMedia($file)->toMediaCollection($collection);
    }

    /**
     * Re-parent an existing media onto a model, keeping its id, uuid and files.
     *
     * This is a trusted, low-level operation: it does not check who owns the
     * media. Client-supplied ids must go through TemporaryMediaService::promote().
     */
    public function attach(Media $media, HasMedia&Model $model, string $collection): Media
    {
        $definition = $model->getMediaCollection($collection);

        if ($definition !== null
            && $definition->acceptsMimeTypes !== []
            && ! in_array($media->mime_type, $definition->acceptsMimeTypes, true)) {
            throw new InvalidArgumentException("Media type [{$media->mime_type}] is not accepted by collection [{$collection}].");
        }

        $media->fill([
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'collection_name' => $collection,
            'order_column' => ((int) $model->media()->where('collection_name', $collection)->max('order_column')) + 1,
        ]);

        // With moves_media_on_update enabled the observer already syncs the path.
        if (! config('media-library.moves_media_on_update')) {
            app(Filesystem::class)->syncMediaPath($media);
        }

        $media->save();

        if ($definition?->singleFile) {
            // File deletion cannot be rolled back, so only run it once the
            // surrounding transaction (if any) has committed.
            DB::afterCommit(fn () => $model->clearMediaCollectionExcept($collection, [$media]));
        }

        return $media;
    }

    /**
     * Remove a media from a model. A media cannot exist without a parent, so
     * this deletes it (and its files).
     */
    public function detach(HasMedia&Model $model, Media $media): void
    {
        if ($media->model_type !== $model->getMorphClass() || (string) $media->model_id !== (string) $model->getKey()) {
            throw new InvalidArgumentException('The media does not belong to the given model.');
        }

        $this->delete($media);
    }

    /**
     * Delete a media; Media Library removes the stored files.
     */
    public function delete(Media $media): void
    {
        $media->delete();
    }
}
