<?php

namespace Modules\Search\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\ClientPackage;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Paid client packages (not pending, as in the center package list) by the client's name/phone or the service name.
 */
class ClientPackageSearchProvider implements GlobalSearchProvider
{
    public function key(): string
    {
        return 'client_packages';
    }

    public function authorize(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageCenterReservations);
    }

    public function search(SearchTerm $term, int $limit, string $locale): Collection
    {
        $like = $term->like();

        return ClientPackage::query()
            ->where('status', '!=', ClientPackageStatus::Pending)
            ->whereHas('service')
            ->where(fn (Builder $query) => $query
                ->whereHas('client', fn (Builder $client) => $client->search($term->value))
                ->orWhereHas('service', fn (Builder $service) => $service
                    ->where('name->en', 'like', $like)
                    ->orWhere('name->ar', 'like', $like)))
            ->latest('id')
            ->limit($limit)
            ->with(['client:id,first_name,last_name,phone_number', 'service:id,center_id,name'])
            ->get()
            ->map(fn (ClientPackage $package): SearchHit => new SearchHit(
                id: $package->id,
                title: $package->client?->full_name ?? '',
                subtitle: $package->service?->getTranslation('name', $locale),
                centerId: $package->service?->center_id,
                meta: [
                    'status' => $package->status?->value,
                    'remaining_sessions' => $package->remaining_sessions,
                    'total_sessions' => $package->total_sessions,
                ],
            ));
    }
}
