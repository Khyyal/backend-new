<?php

namespace Modules\Clients\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;

class FavoriteService
{
    public function isFavorited(Client $client, Center $center): bool
    {
        return $client->favoriteCenters()
            ->whereKey($center->getKey())
            ->exists();
    }

    public function add(Client $client, Center $center): void
    {
        $client->favoriteCenters()->syncWithoutDetaching([$center->getKey()]);
    }

    public function remove(Client $client, Center $center): bool
    {
        return $client->favoriteCenters()->detach($center->getKey()) > 0;
    }

    public function list(Client $client): LengthAwarePaginator
    {
        return $client->favoriteCenters()
            ->withRatingStats()
            ->with(['city', 'tags', 'media'])
            ->orderByDesc('center_favorites.created_at')
            ->paginate(15);
    }
}
