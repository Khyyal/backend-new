<?php

namespace Modules\Support\Services\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Support\Enums\MediaStatus;
use Modules\Support\Exceptions\InvalidTemporaryMediaException;
use Modules\Support\Models\TemporaryUpload;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Lifecycle of temporary uploads.
 *
 * Transaction boundaries: database rows are changed inside transactions with
 * the temporary_uploads row locked, so promote / discard / cleanup serialize
 * per upload. Storage is not transactional: file writes happen outside any
 * transaction and are compensated by deleting the upload on failure; file
 * deletions are performed by Media Library when the upload is deleted and, if
 * one fails, the transaction rolls back so the next cleanup run retries it.
 */
class TemporaryMediaService
{
    public function __construct(private MediaService $mediaService) {}

    public function statusOf(Media $media): MediaStatus
    {
        return $media->model_type === (new TemporaryUpload)->getMorphClass()
            ? MediaStatus::Temporary
            : MediaStatus::Attached;
    }

    /**
     * Store a file as temporary media owned by $uploader.
     */
    public function createTemporary(UploadedFile $file, Model $uploader): Media
    {
        $upload = TemporaryUpload::create([
            'uploader_id' => $uploader->getKey(),
            'uploader_type' => $uploader->getMorphClass(),
            'expires_at' => now()->addMinutes(config('support-media.temporary_ttl_minutes')),
        ]);

        try {
            $media = $this->mediaService->upload($upload, $file, config('support-media.temporary_collection'));
        } catch (Throwable $e) {
            // Deleting the upload also removes any media/files already written.
            $upload->delete();

            throw $e;
        }

        return $media->setRelation('model', $upload);
    }

    /**
     * Attach a temporary media (identified by its uuid) to a model.
     *
     * @throws InvalidTemporaryMediaException
     */
    public function promote(string $mediaId, Model $owner, HasMedia&Model $model, string $collection): Media
    {
        return $this->promoteMany([$mediaId], $owner, $model, $collection)->first();
    }

    /**
     * Attach several temporary media atomically (all or none).
     *
     * @param  array<int, string>  $mediaIds
     * @return Collection<int, Media> in the order of $mediaIds
     *
     * @throws InvalidTemporaryMediaException
     */
    public function promoteMany(array $mediaIds, Model $owner, HasMedia&Model $model, string $collection): Collection
    {
        $mediaIds = array_values(array_unique($mediaIds));

        return DB::transaction(function () use ($mediaIds, $owner, $model, $collection) {
            $uploads = TemporaryUpload::query()
                ->whereHas('media', fn ($query) => $query->whereIn('uuid', $mediaIds))
                ->with('media')
                ->lockForUpdate()
                ->get();

            if ($uploads->count() !== count($mediaIds)) {
                throw new InvalidTemporaryMediaException;
            }

            foreach ($uploads as $upload) {
                if (! $upload->isOwnedBy($owner) || $upload->isExpired()) {
                    throw new InvalidTemporaryMediaException;
                }
            }

            // Claim: removing the rows (without model events, so the media is
            // kept) is what prevents a second promotion of the same media.
            if (TemporaryUpload::whereKey($uploads->modelKeys())->delete() !== $uploads->count()) {
                throw new InvalidTemporaryMediaException;
            }

            $byUuid = $uploads->map(fn (TemporaryUpload $upload) => $upload->media->first())->keyBy('uuid');

            return collect($mediaIds)->map(
                fn (string $uuid) => $this->mediaService->attach($byUuid[$uuid], $model, $collection)
            );
        });
    }

    /**
     * Delete a still-temporary media owned by $owner.
     *
     * @throws InvalidTemporaryMediaException
     */
    public function discard(Media $media, Model $owner): void
    {
        DB::transaction(function () use ($media, $owner) {
            $upload = $this->statusOf($media) === MediaStatus::Temporary
                ? TemporaryUpload::query()->lockForUpdate()->find($media->model_id)
                : null;

            if ($upload === null || ! $upload->isOwnedBy($owner)) {
                throw new InvalidTemporaryMediaException;
            }

            $upload->delete();
        });
    }

    /**
     * Expire an upload immediately, deleting its media. Returns false if it
     * was already gone (promoted, discarded or cleaned up concurrently).
     */
    public function expire(TemporaryUpload $upload): bool
    {
        return $this->deleteLocked($upload->getKey(), onlyIfExpired: false);
    }

    /**
     * Delete every expired upload and its media. Idempotent; processes rows in
     * id-ordered chunks so memory stays flat.
     *
     * @return int number of uploads removed
     */
    public function cleanupExpired(): int
    {
        $removed = 0;

        TemporaryUpload::query()
            ->where('expires_at', '<=', now())
            ->chunkById(config('support-media.cleanup_chunk_size'), function ($uploads) use (&$removed) {
                foreach ($uploads as $upload) {
                    try {
                        $removed += $this->deleteLocked($upload->getKey(), onlyIfExpired: true) ? 1 : 0;
                    } catch (Throwable $e) {
                        report($e); // left in place, retried on the next run
                    }
                }
            });

        return $removed;
    }

    private function deleteLocked(int|string $id, bool $onlyIfExpired): bool
    {
        return DB::transaction(function () use ($id, $onlyIfExpired) {
            $upload = TemporaryUpload::query()
                ->when($onlyIfExpired, fn ($query) => $query->where('expires_at', '<=', now()))
                ->lockForUpdate()
                ->find($id);

            if ($upload === null) {
                return false;
            }

            $upload->delete();

            return true;
        });
    }
}
