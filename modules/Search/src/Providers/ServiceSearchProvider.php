<?php

namespace Modules\Search\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Center services by name (en/ar) or slug. `kind` is the admin route segment
 * (`admin/centers/{center}/<kind>`) and `meta.serviceable_id` the id under it.
 */
class ServiceSearchProvider implements GlobalSearchProvider
{
    /**
     * @var array<string, string>
     */
    private const KINDS = [
        ServiceType::RecreationRiding->value => 'recreation-ridings',
        ServiceType::Visit->value => 'visits',
        ServiceType::Event->value => 'events',
        ServiceType::Resort->value => 'resorts',
        ServiceType::HorseCare->value => 'horse-cares',
        ServiceType::HorseService->value => 'horse-services',
        ServiceType::Training->value => 'training-services',
        ServiceType::Membership->value => 'memberships',
    ];

    public function key(): string
    {
        return 'services';
    }

    public function authorize(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageCenterServices);
    }

    public function search(SearchTerm $term, int $limit, string $locale): Collection
    {
        $like = $term->like();

        return Service::query()
            ->where(fn (Builder $query) => $query
                ->where('name->en', 'like', $like)
                ->orWhere('name->ar', 'like', $like)
                ->orWhere('slug', 'like', $like))
            ->latest('id')
            ->limit($limit)
            ->with('center:id,name')
            ->get()
            ->map(function (Service $service) use ($locale): SearchHit {
                $type = $service->type instanceof ServiceType ? $service->type->value : (string) $service->type;

                return new SearchHit(
                    id: $service->id,
                    title: (string) $service->getTranslation('name', $locale),
                    subtitle: $service->center?->name,
                    centerId: $service->center_id,
                    kind: self::KINDS[$type] ?? null,
                    meta: [
                        'type' => $type,
                        'serviceable_id' => $service->serviceable_id,
                        'status' => $service->status instanceof \BackedEnum ? $service->status->value : $service->status,
                    ],
                );
            });
    }
}
