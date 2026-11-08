<?php

namespace Modules\Settlements\Exports;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Models\Center;
use Modules\Exports\Exports\QueuedExport;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Services\CenterLedgerQuery;

/**
 * A center's ledger entries, filtered like the admin ledger list. Positive
 * amounts are owed to the center, negative ones to the platform.
 */
class CenterLedgerExport extends QueuedExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(array $filters, public readonly int $centerId)
    {
        parent::__construct($filters);
    }

    /**
     * @return Builder<CenterLedgerEntry>
     */
    public function query(): Builder
    {
        return app(CenterLedgerQuery::class)->build(Center::query()->findOrFail($this->centerId), $this->filters);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['ID', 'Created at (UTC)', 'Type', 'Purchase ID', 'Payment ID', 'Amount (SAR)', 'Available at (UTC)', 'Payout ID'];
    }

    /**
     * @param  CenterLedgerEntry  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id,
            $this->dateTime($row->created_at),
            $this->enumValue($row->type),
            $row->purchase_id,
            $row->payment_id,
            $this->money($row->amount),
            $this->dateTime($row->available_at),
            $row->payout_id,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return ['F' => self::MONEY_FORMAT];
    }
}
