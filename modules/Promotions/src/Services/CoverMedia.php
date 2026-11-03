<?php

namespace Modules\Promotions\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Support\Services\Media\MediaService;
use Modules\Support\Services\Media\TemporaryMediaService;
use Spatie\MediaLibrary\HasMedia;

/**
 * The optional `cover` image of promotions and campaigns. Requests send a
 * temporary media id (see the Support media API); null removes the cover.
 */
class CoverMedia
{
    public function __construct(
        private readonly TemporaryMediaService $temporaryMedia,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input; nothing happens without a `cover` key
     */
    public function sync(HasMedia&Model $model, Model $actor, array $data): void
    {
        if (! array_key_exists('cover', $data)) {
            return;
        }

        $current = $model->getFirstMedia('cover');

        if ($data['cover'] === null) {
            if ($current !== null) {
                DB::afterCommit(fn () => $this->mediaService->detach($model, $current));
            }

            return;
        }

        // The collection is singleFile: promoting replaces the previous cover.
        if ($current?->uuid !== $data['cover']) {
            $this->temporaryMedia->promote($data['cover'], $actor, $model, 'cover');
        }
    }
}
