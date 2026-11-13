<?php

namespace Modules\Reservations\Http\Resources\Concerns;

use Modules\Centers\Models\Horse;
use Modules\Clients\Models\Client;
use Modules\Services\Models\Trainer;
use Modules\Support\Http\Resources\MediaResource;

/**
 * Shared horse shapes of the horses and stays resources. Expects the horse's
 * `media` (and `client` / `trainers` where used) to be eager-loaded.
 */
trait DescribesHorse
{
    /**
     * @return array{id: int, name: string, image: MediaResource|null, room: int}
     */
    protected function horseSummary(Horse $horse): array
    {
        $image = $horse->media->firstWhere('collection_name', 'image');

        return [
            'id' => $horse->id,
            'name' => $horse->name,
            'image' => $image === null ? null : new MediaResource($image),
            'room' => $horse->room,
        ];
    }

    /**
     * @return array{id: int, first_name: string|null, last_name: string|null, phone_number: string}|null
     */
    protected function horseClient(?Client $client): ?array
    {
        return $client === null ? null : [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'phone_number' => $client->phone_number,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function horseTrainers(Horse $horse): array
    {
        return $horse->trainers->map(fn (Trainer $trainer) => [
            'id' => $trainer->id,
            'name' => $trainer->name,
        ])->values()->all();
    }
}
