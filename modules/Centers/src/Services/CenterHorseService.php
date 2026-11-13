<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\Horse;
use Modules\Support\Services\Media\MediaService;
use Modules\Support\Services\Media\TemporaryMediaService;

/**
 * Center-facing CRUD for horses. A horse given a `client_id` is owned by
 * that client, who is attached to the center (center_client) so the center
 * can see them; without one the center owns the horse. Image files are only
 * removed after commit.
 */
class CenterHorseService
{
    private const RELATIONS = ['client', 'media'];

    public function __construct(
        private readonly TemporaryMediaService $temporaryMedia,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * @param  array{name: string, room: int, client_id?: int|null, image?: string|null}  $data
     */
    public function create(Center $center, Model $user, array $data): Horse
    {
        $horse = DB::transaction(function () use ($center, $user, $data) {
            $horse = $center->horses()->create([
                'name' => $data['name'],
                'room' => $data['room'],
                'client_id' => $data['client_id'] ?? null,
            ]);

            $this->attachClient($center, $horse);
            $this->syncImage($horse, $user, $data);

            return $horse;
        });

        return $horse->load(self::RELATIONS);
    }

    /**
     * @param  array{name: string, room: int, client_id?: int|null, image?: string|null}  $data
     */
    public function update(Center $center, Horse $horse, Model $user, array $data): Horse
    {
        DB::transaction(function () use ($center, $horse, $user, $data) {
            $locked = Horse::query()->lockForUpdate()->findOrFail($horse->getKey());

            $locked->update([
                'name' => $data['name'],
                'room' => $data['room'],
                'client_id' => $data['client_id'] ?? null,
            ]);

            $this->attachClient($center, $locked);
            $this->syncImage($locked, $user, $data);
        });

        // Loaded after commit so an image removed in afterCommit is not returned.
        return $horse->refresh()->load(self::RELATIONS);
    }

    public function delete(Horse $horse): void
    {
        $horse->delete();
    }

    /**
     * @param  array{owner?: string|null, client_id?: int|null, q?: string|null}  $filters
     * @return Builder<Horse>
     */
    public function queryForCenter(Center $center, array $filters = []): Builder
    {
        return Horse::query()
            ->where('center_id', $center->getKey())
            ->when(($filters['owner'] ?? null) === 'center', fn (Builder $query) => $query->whereNull('client_id'))
            ->when(($filters['owner'] ?? null) === 'client', fn (Builder $query) => $query->whereNotNull('client_id'))
            ->when($filters['client_id'] ?? null, fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when(filled($filters['q'] ?? null), fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%'))
            ->with(self::RELATIONS);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findForCenter(Center $center, int|string $id): Horse
    {
        return $this->queryForCenter($center)->whereKey($id)->firstOrFail();
    }

    private function attachClient(Center $center, Horse $horse): void
    {
        if ($horse->client_id !== null) {
            $center->clients()->syncWithoutDetaching([$horse->client_id]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncImage(Horse $horse, Model $user, array $data): void
    {
        if (! array_key_exists('image', $data)) {
            return;
        }

        $current = $horse->media()->where('collection_name', 'image')->first();

        if ($data['image'] === null) {
            if ($current !== null) {
                DB::afterCommit(fn () => $this->mediaService->detach($horse, $current));
            }

            return;
        }

        if ($current?->uuid !== $data['image']) {
            // The collection is singleFile: attaching removes the previous image.
            $this->temporaryMedia->promote($data['image'], $user, $horse, 'image');
        }
    }
}
