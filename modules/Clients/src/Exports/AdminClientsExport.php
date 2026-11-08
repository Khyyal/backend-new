<?php

namespace Modules\Clients\Exports;

use Illuminate\Database\Eloquent\Builder;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\AdminClientQuery;
use Modules\Exports\Exports\QueuedExport;

/**
 * Every client across centers, filtered like the admin client list.
 */
class AdminClientsExport extends QueuedExport
{
    /**
     * @return Builder<Client>
     */
    public function query(): Builder
    {
        return app(AdminClientQuery::class)->build($this->filters);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['ID', 'First name', 'Last name', 'Phone', 'Gender', 'City', 'Registered at (UTC)', 'Centers', 'Reservations', 'Packages', 'Memberships'];
    }

    /**
     * @param  Client  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id,
            $row->first_name,
            $row->last_name,
            $row->phone_number,
            $row->gender,
            $row->city?->name,
            $this->dateTime($row->created_at),
            $row->centers_count,
            $row->reservations_count,
            $row->packages_count,
            $row->memberships_count,
        ];
    }
}
