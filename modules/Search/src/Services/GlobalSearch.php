<?php

namespace Modules\Search\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\SearchServiceProvider;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Models\SystemUser;

/**
 * Runs every search provider the user may see and keys the hits by group.
 */
class GlobalSearch
{
    public function __construct(private Container $container) {}

    /**
     * @return array<string, Collection<int, SearchHit>>
     */
    public function search(SystemUser $user, string $term, int $limit, string $locale): array
    {
        if (! $user->isActive()) {
            return [];
        }

        $searchTerm = new SearchTerm($term);
        $groups = [];

        /** @var GlobalSearchProvider $provider */
        foreach ($this->container->tagged(SearchServiceProvider::PROVIDERS_TAG) as $provider) {
            if ($provider->authorize($user)) {
                $groups[$provider->key()] = $provider->search($searchTerm, $limit, $locale);
            }
        }

        return $groups;
    }
}
