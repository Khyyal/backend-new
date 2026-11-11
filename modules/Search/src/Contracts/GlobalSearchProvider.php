<?php

namespace Modules\Search\Contracts;

use Illuminate\Support\Collection;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Models\SystemUser;

/**
 * One result group of the admin global search.
 */
interface GlobalSearchProvider
{
    /**
     * The group key in the response, e.g. `reservations`.
     */
    public function key(): string;

    /**
     * Whether the (active) system user may see this group.
     */
    public function authorize(SystemUser $user): bool;

    /**
     * @return Collection<int, SearchHit>
     */
    public function search(SearchTerm $term, int $limit, string $locale): Collection;
}
