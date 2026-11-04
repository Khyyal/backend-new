<?php

namespace Modules\Homepage\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Homepage\Models\Banner;
use Modules\Support\Services\Media\MediaService;
use Modules\Support\Services\Media\TemporaryMediaService;

/**
 * The per-locale artwork of a banner. Requests send `images: {en: uuid|null, ar: uuid|null}` with
 * temporary media ids (see the Support media API); null removes that locale's image, a missing
 * locale is left as it is.
 */
class BannerImages
{
    public function __construct(
        private readonly TemporaryMediaService $temporaryMedia,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input; nothing happens without an `images` key
     */
    public function sync(Banner $banner, Model $actor, array $data): void
    {
        foreach ($data['images'] ?? [] as $locale => $mediaId) {
            $collection = Banner::imageCollection($locale);
            $current = $banner->getFirstMedia($collection);

            if ($mediaId === null) {
                if ($current !== null) {
                    DB::afterCommit(fn () => $this->mediaService->detach($banner, $current));
                }

                continue;
            }

            // The collection is singleFile: promoting replaces the previous image.
            if ($current?->uuid !== $mediaId) {
                $this->temporaryMedia->promote($mediaId, $actor, $banner, $collection);
            }
        }
    }
}
