<?php

namespace Modules\Reservations\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\Horse;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\ServiceType;

/**
 * A center's horses board: its active stabling stays (confirmed and started)
 * and its horses without an active stay, in one list ordered by horse name.
 * Each page item is either a Reservation (a stay) or a Horse.
 */
class HorseStayBoardQuery
{
    public const TYPE_ALL = 'all';

    public const TYPE_ACTIVE = 'active';

    public const TYPE_OTHERS = 'others';

    public const TYPES = [self::TYPE_ALL, self::TYPE_ACTIVE, self::TYPE_OTHERS];

    /**
     * @param  array{type?: string|null, q?: string|null}  $filters
     * @return LengthAwarePaginator<int, Reservation|Horse>
     */
    public function paginate(Center $center, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $type = $filters['type'] ?? self::TYPE_ALL;
        $search = $filters['q'] ?? null;

        $query = match ($type) {
            self::TYPE_ACTIVE => $this->stays($center, $search),
            self::TYPE_OTHERS => $this->unlinkedHorses($center, $search),
            default => $this->stays($center, $search)->unionAll($this->unlinkedHorses($center, $search)),
        };

        $page = DB::query()->fromSub($query, 'board')
            ->orderBy('horse_name')
            ->orderBy('kind', 'desc')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return $page->setCollection($this->hydrate($page->getCollection()->all()));
    }

    private function stays(Center $center, ?string $search): Builder
    {
        return $this->activeStays($center)
            ->join('horses', 'horses.id', '=', 'reservations.horse_id')
            ->leftJoin('clients', 'clients.id', '=', 'reservations.client_id')
            ->tap(fn (Builder $query) => $this->search($query, $search))
            ->select([DB::raw("'stay' as kind"), 'reservations.id', 'horses.name as horse_name']);
    }

    private function unlinkedHorses(Center $center, ?string $search): Builder
    {
        return DB::table('horses')
            ->leftJoin('clients', 'clients.id', '=', 'horses.client_id')
            ->where('horses.center_id', $center->getKey())
            ->whereNull('horses.deleted_at')
            ->whereNotExists(fn (Builder $active) => $this->constrainActiveStays($active->from('reservations'), $center)
                ->whereColumn('reservations.horse_id', 'horses.id')
                ->select(DB::raw(1)))
            ->tap(fn (Builder $query) => $this->search($query, $search))
            ->select([DB::raw("'horse' as kind"), 'horses.id', 'horses.name as horse_name']);
    }

    /**
     * Confirmed stabling stays of the center that have started.
     */
    private function activeStays(Center $center): Builder
    {
        return $this->constrainActiveStays(DB::table('reservations'), $center);
    }

    private function constrainActiveStays(Builder $reservations, Center $center): Builder
    {
        return $reservations
            ->join('services', 'services.id', '=', 'reservations.service_id')
            ->where('services.type', ServiceType::Stabling->value)
            ->where('reservations.center_id', $center->getKey())
            ->where('reservations.status', ReservationStatus::Confirmed->value)
            ->whereDate('reservations.date', '<=', today()->toDateString())
            ->whereNull('reservations.deleted_at');
    }

    /**
     * Every word must match the horse name or the client's name or phone.
     */
    private function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn (Builder $match) => $match
                ->where('horses.name', 'like', $like)
                ->orWhere('clients.first_name', 'like', $like)
                ->orWhere('clients.last_name', 'like', $like)
                ->orWhere('clients.phone_number', 'like', $like));
        }
    }

    /**
     * @param  array<int, object{kind: string, id: int}>  $rows
     * @return Collection<int, Reservation|Horse>
     */
    private function hydrate(array $rows): Collection
    {
        $ids = collect($rows)->groupBy('kind')->map(fn ($group) => $group->pluck('id')->all());

        $stays = Reservation::query()
            ->whereKey($ids->get('stay', []))
            ->with(['horse.media', 'horse.client', 'service'])
            ->withCount([
                'purchaseItems as unpaid_stay_count' => fn (EloquentBuilder $items) => $items
                    ->whereHas('purchase', fn (EloquentBuilder $purchase) => $purchase->where('status', PurchaseStatus::Pending)),
                'invoices as unpaid_invoices_count' => fn (EloquentBuilder $invoices) => $invoices
                    ->whereHas('purchaseItems.purchase', fn (EloquentBuilder $purchase) => $purchase->where('status', PurchaseStatus::Pending)),
            ])
            ->get()
            ->keyBy('id');

        $horses = Horse::query()
            ->whereKey($ids->get('horse', []))
            ->with(['client', 'media', 'trainers'])
            ->get()
            ->keyBy('id');

        return collect($rows)->map(fn (object $row) => $row->kind === 'stay' ? $stays->get($row->id) : $horses->get($row->id))->filter()->values();
    }
}
