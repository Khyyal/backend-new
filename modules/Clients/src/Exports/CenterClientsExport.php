<?php

namespace Modules\Clients\Exports;

use Illuminate\Database\Query\Builder;
use Modules\Centers\Models\Center;
use Modules\Clients\Services\CenterClientQuery;
use Modules\Exports\Exports\QueuedExport;

/**
 * A center's clients with their confirmed/completed purchase stats, filtered
 * like the center client list.
 */
class CenterClientsExport extends QueuedExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(array $filters, public readonly int $centerId)
    {
        parent::__construct($filters);
    }

    public function query(): Builder
    {
        return app(CenterClientQuery::class)->build(Center::query()->findOrFail($this->centerId), $this->filters);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['ID', 'First name', 'Last name', 'Phone', 'First purchase at (UTC)', 'Last purchase at (UTC)', 'Purchases'];
    }

    /**
     * @param  object{id: int, first_name: ?string, last_name: ?string, phone_number: string, first_purchase_at: ?string, last_purchase_at: ?string, purchases_count: int}  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id,
            $row->first_name,
            $row->last_name,
            $row->phone_number,
            $this->dateTime($row->first_purchase_at),
            $this->dateTime($row->last_purchase_at),
            (int) $row->purchases_count,
        ];
    }
}
