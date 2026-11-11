<?php

namespace Modules\Search\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Reservations\Models\Reservation;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Reservations by public code (exact, listed first) or by the client's name/phone.
 */
class ReservationSearchProvider implements GlobalSearchProvider
{
    public function key(): string
    {
        return 'reservations';
    }

    public function authorize(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageCenterReservations);
    }

    public function search(SearchTerm $term, int $limit, string $locale): Collection
    {
        $isCode = $term->looksLikeCode();

        return Reservation::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('client', fn (Builder $client) => $client->search($term->value))
                ->when($isCode, fn (Builder $query) => $query->orWhere->whereCode($term->code())))
            ->when($isCode, fn (Builder $query) => $query->orderByRaw('case when code = ? then 0 else 1 end', [$term->code()]))
            ->latest('id')
            ->limit($limit)
            ->with(['client:id,first_name,last_name,phone_number', 'service:id,name'])
            ->get()
            ->map(fn (Reservation $reservation): SearchHit => new SearchHit(
                id: $reservation->id,
                title: $reservation->client?->full_name ?? $reservation->code,
                subtitle: collect([
                    $reservation->service?->getTranslation('name', $locale),
                    $reservation->date?->toDateString(),
                ])->filter()->implode(' · '),
                code: $reservation->code,
                centerId: $reservation->center_id,
                meta: ['status' => $reservation->status?->value, 'date' => $reservation->date?->toDateString()],
            ));
    }
}
