<?php

namespace Modules\Search\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Centers\Models\Center;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Centers by name, slug, contact phone or email.
 */
class CenterSearchProvider implements GlobalSearchProvider
{
    public function key(): string
    {
        return 'centers';
    }

    public function authorize(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewAnyCenters);
    }

    public function search(SearchTerm $term, int $limit, string $locale): Collection
    {
        $like = $term->like();

        return Center::query()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('contact_phone', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Center $center): SearchHit => new SearchHit(
                id: $center->id,
                title: $center->name,
                subtitle: $center->contact_phone ?? $center->email,
                centerId: $center->id,
                meta: ['status' => $center->status?->value],
            ));
    }
}
