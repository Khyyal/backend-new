<?php

namespace Modules\Services\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Services\Models\HorseService;
use Modules\Services\Models\Service;
use Modules\Support\Services\Media\MediaService;
use Modules\Support\Services\Media\TemporaryMediaService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Center-facing CRUD for horse services: delegates the domain graph to
 * HorseServiceService and the cover / images to the media services, all
 * inside one transaction. Stored files are only deleted after commit.
 */
class CenterHorseServiceService
{
    private const RELATIONS = ['service.priceOptions', 'service.media'];

    public function __construct(
        private readonly HorseServiceService $horseServiceService,
        private readonly TemporaryMediaService $temporaryMedia,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Center $center, User $user, array $data): HorseService
    {
        $horseService = DB::transaction(function () use ($center, $user, $data) {
            $horseService = $this->horseServiceService->create([...$data, 'center_id' => $center->getKey()]);

            $this->syncMedia($horseService->service, $user, $data);

            return $horseService;
        });

        return $horseService->load(self::RELATIONS);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Center $center, HorseService $horseService, User $user, array $data): HorseService
    {
        $horseService = DB::transaction(function () use ($horseService, $user, $data) {
            $horseService = $this->horseServiceService->update($horseService, $data);

            // Serialize concurrent media changes of the same service.
            $service = Service::query()->lockForUpdate()->findOrFail($horseService->service->getKey());
            $this->syncMedia($service, $user, $data);

            return $horseService;
        });

        // Loaded after commit so files removed in afterCommit callbacks are not returned.
        return $horseService->unsetRelations()->load(self::RELATIONS);
    }

    public function delete(HorseService $horseService): void
    {
        $this->horseServiceService->delete($horseService);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findForCenter(Center $center, int|string $id): HorseService
    {
        return HorseService::query()
            ->whereKey($id)
            ->whereHas('service', fn (Builder $query) => $query->where('center_id', $center->getKey()))
            ->with(self::RELATIONS)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMedia(Service $service, User $user, array $data): void
    {
        if (array_key_exists('cover', $data)) {
            $this->syncCover($service, $user, $data['cover']);
        }

        if (array_key_exists('images', $data)) {
            $this->syncImages($service, $user, $data['images']);
        }
    }

    private function syncCover(Service $service, User $user, ?string $mediaId): void
    {
        $current = $service->media()->where('collection_name', 'cover')->first();

        if ($mediaId === null) {
            if ($current !== null) {
                DB::afterCommit(fn () => $this->mediaService->detach($service, $current));
            }

            return;
        }

        if ($current?->uuid !== $mediaId) {
            // The collection is singleFile: attach() removes the previous media.
            $this->temporaryMedia->promote($mediaId, $user, $service, 'cover');
        }
    }

    /**
     * @param  array<int, string>  $mediaIds  desired images, in display order
     */
    private function syncImages(Service $service, User $user, array $mediaIds): void
    {
        $current = $service->media()->where('collection_name', 'images')->orderBy('order_column')->get()->toBase()->keyBy('uuid');
        $newIds = array_values(array_diff($mediaIds, $current->keys()->all()));

        $promoted = $newIds === []
            ? collect()
            : $this->temporaryMedia->promoteMany($newIds, $user, $service, 'images')->keyBy('uuid');

        $removed = $current->except($mediaIds);

        $alreadyOrdered = $newIds === []
            && $current->except($removed->keys()->all())->keys()->values()->all() === $mediaIds;

        if (! $alreadyOrdered && $mediaIds !== []) {
            Media::setNewOrder(collect($mediaIds)->map(fn (string $id) => ($current[$id] ?? $promoted[$id])->id)->all());
        }

        if ($removed->isNotEmpty()) {
            DB::afterCommit(fn () => $removed->each(fn (Media $media) => $this->mediaService->detach($service, $media)));
        }
    }
}
