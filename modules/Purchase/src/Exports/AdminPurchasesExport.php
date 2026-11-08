<?php

namespace Modules\Purchase\Exports;

use Illuminate\Database\Eloquent\Builder;
use Modules\Exports\Exports\QueuedExport;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\AdminPurchaseQuery;

/**
 * Every client purchase from a center, filtered like the admin purchase list.
 */
class AdminPurchasesExport extends QueuedExport
{
    /**
     * @return Builder<Purchase>
     */
    public function query(): Builder
    {
        return app(AdminPurchaseQuery::class)->build($this->filters);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Created at (UTC)',
            'Center',
            'Client',
            'Client phone',
            'Status',
            'Source',
            'Channel',
            'Items',
            'Subtotal (SAR)',
            'Discount (SAR)',
            'Tax (SAR)',
            'Total (SAR)',
            'Platform fee (SAR)',
            'Payment method',
            'Payment status',
        ];
    }

    /**
     * @param  Purchase  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id,
            $this->dateTime($row->created_at),
            $row->merchant?->name,
            $row->buyer?->full_name,
            $row->buyer?->phone_number,
            $this->enumValue($row->status),
            $this->enumValue($row->source),
            $this->enumValue($row->channel),
            $row->items_count,
            $this->money($row->subtotal),
            $this->money($row->discount_amount),
            $this->money($row->tax_amount),
            $this->money($row->total_amount),
            $this->money($row->fee?->owedAmount() ?? 0),
            $this->enumValue($row->latestPayment?->method),
            $this->enumValue($row->latestPayment?->status),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return array_fill_keys(['J', 'K', 'L', 'M', 'N'], self::MONEY_FORMAT);
    }
}
