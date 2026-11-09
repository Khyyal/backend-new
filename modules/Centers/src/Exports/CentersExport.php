<?php

namespace Modules\Centers\Exports;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAdminService;
use Modules\Exports\Exports\QueuedExport;

/**
 * Every center, filtered like the admin center list.
 */
class CentersExport extends QueuedExport
{
    /**
     * @return Builder<Center>
     */
    public function query(): Builder
    {
        return app(CenterAdminService::class)->query($this->filters)->with('city');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['ID', 'Name', 'Slug', 'City', 'Contact phone', 'Email', 'Address', 'State', 'Status', 'Custom site', 'Domain', 'Created at (UTC)'];
    }

    /**
     * @param  Center  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id,
            $row->name,
            $row->slug,
            $row->city?->name,
            $row->contact_phone,
            $row->email,
            $row->address,
            $this->enumValue($row->state),
            $this->enumValue($row->status),
            $row->custom_site_enabled ? 'yes' : 'no',
            $row->domain,
            $this->dateTime($row->created_at),
        ];
    }
}
