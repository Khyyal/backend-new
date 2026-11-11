<?php

namespace Modules\Search\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Paid client memberships (not pending) by the client's name/phone or the service name.
 */
class ClientMembershipSearchProvider implements GlobalSearchProvider
{
    public function key(): string
    {
        return 'client_memberships';
    }

    public function authorize(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageCenterReservations);
    }

    public function search(SearchTerm $term, int $limit, string $locale): Collection
    {
        $like = $term->like();

        return ClientMembership::query()
            ->where('status', '!=', ClientMembershipStatus::Pending)
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
            ->map(fn (ClientMembership $membership): SearchHit => new SearchHit(
                id: $membership->id,
                title: $membership->client?->full_name ?? '',
                subtitle: $membership->service?->getTranslation('name', $locale),
                centerId: $membership->service?->center_id,
                meta: [
                    'status' => $membership->status?->value,
                    'expires_at' => $membership->expires_at?->toIso8601String(),
                ],
            ));
    }
}
