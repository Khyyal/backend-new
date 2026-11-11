<?php

namespace Modules\Search\Providers;

use Illuminate\Support\Collection;
use Modules\Clients\Models\Client;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Clients by name and/or phone number (Client::scopeSearch).
 */
class ClientSearchProvider implements GlobalSearchProvider
{
    public function key(): string
    {
        return 'clients';
    }

    public function authorize(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewAnyClients);
    }

    public function search(SearchTerm $term, int $limit, string $locale): Collection
    {
        return Client::query()
            ->search($term->value)
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'phone_number'])
            ->map(fn (Client $client): SearchHit => new SearchHit(
                id: $client->id,
                title: $client->full_name !== '' ? $client->full_name : (string) $client->phone_number,
                subtitle: $client->phone_number,
            ));
    }
}
