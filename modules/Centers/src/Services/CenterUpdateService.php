<?php

namespace Modules\Centers\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Services\Media\MediaService;
use Modules\Support\Services\Media\TemporaryMediaService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CenterUpdateService
{
    private const ATTRIBUTES = ['name', 'description', 'lat', 'lng', 'address', 'contact_phone'];

    public function __construct(
        private readonly TemporaryMediaService $temporaryMedia,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * Update a center. Only keys present in $data are touched.
     *
     * Everything runs in one transaction; stored files that must be removed
     * are deleted after commit since storage cannot be rolled back.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Center $center, User $user, array $data): Center
    {
        return DB::transaction(function () use ($center, $user, $data) {
            // Serialize concurrent updates of the same center's media.
            $center = Center::query()->lockForUpdate()->findOrFail($center->getKey());

            $center->fill(Arr::only($data, self::ATTRIBUTES))->save();

            if (array_key_exists('tags', $data)) {
                $center->tags()->sync($data['tags']);
            }

            foreach (['logo', 'cover'] as $collection) {
                if (array_key_exists($collection, $data)) {
                    $this->syncSingleMedia($center, $user, $collection, $data[$collection]);
                }
            }

            if (array_key_exists('images', $data)) {
                $this->syncImages($center, $user, $data['images']);
            }

            return $center->load(['city', 'tags', 'media']);
        });
    }

    private function syncSingleMedia(Center $center, User $user, string $collection, ?string $mediaId): void
    {
        $current = $center->media()->where('collection_name', $collection)->first();

        if ($mediaId === null) {
            if ($current !== null) {
                DB::afterCommit(fn () => $this->mediaService->detach($center, $current));
            }

            return;
        }

        if ($current?->uuid === $mediaId) {
            return;
        }

        // The collection is singleFile: attach() removes the previous media.
        $this->temporaryMedia->promote($mediaId, $user, $center, $collection);
    }

    /**
     * @param  array<int, string>  $mediaIds  desired images, in display order
     */
    private function syncImages(Center $center, User $user, array $mediaIds): void
    {
        $current = $center->media()->where('collection_name', 'images')->orderBy('order_column')->get()->toBase()->keyBy('uuid');
        $newIds = array_values(array_diff($mediaIds, $current->keys()->all()));

        $promoted = $newIds === []
            ? collect()
            : $this->temporaryMedia->promoteMany($newIds, $user, $center, 'images')->keyBy('uuid');

        $removed = $current->except($mediaIds);

        $alreadyOrdered = $newIds === []
            && $current->except($removed->keys()->all())->keys()->values()->all() === $mediaIds;

        if (! $alreadyOrdered && $mediaIds !== []) {
            $ordered = collect($mediaIds)->map(fn (string $id) => $current[$id] ?? $promoted[$id]);

            Media::setNewOrder($ordered->pluck('id')->all());
        }

        if ($removed->isNotEmpty()) {
            DB::afterCommit(fn () => $removed->each(fn (Media $media) => $this->mediaService->detach($center, $media)));
        }
    }
}
