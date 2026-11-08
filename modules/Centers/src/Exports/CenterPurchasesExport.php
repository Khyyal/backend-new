<?php

namespace Modules\Centers\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterPurchaseQuery;
use Modules\Exports\Exports\QueuedExport;
use Modules\Purchase\Models\Purchase;
use Modules\Support\Models\SystemUser;

/**
 * A center's purchases the requester may see, filtered like the center
 * purchase list. Amount columns are left out when the requester may not see
 * prices.
 */
class CenterPurchasesExport extends QueuedExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        array $filters,
        public readonly int $centerId,
        public readonly string $requesterType,
        public readonly int $requesterId,
        public readonly bool $includePrices,
    ) {
        parent::__construct($filters);
    }

    /**
     * @return Builder<Purchase>
     */
    public function query(): Builder
    {
        /** @var class-string<User|SystemUser> $requesterClass */
        $requesterClass = Relation::getMorphedModel($this->requesterType) ?? $this->requesterType;

        return app(CenterPurchaseQuery::class)->build(
            Center::query()->findOrFail($this->centerId),
            $requesterClass::query()->findOrFail($this->requesterId),
            $this->filters,
        );
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $headings = ['ID', 'Created at (UTC)', 'Client', 'Client phone', 'Status', 'Source', 'Channel', 'Items', 'Payment method', 'Payment status'];

        if ($this->includePrices) {
            array_push($headings, 'Subtotal (SAR)', 'Discount (SAR)', 'Tax (SAR)', 'Total (SAR)', 'Platform fee (SAR)');
        }

        return $headings;
    }

    /**
     * @param  Purchase  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        $values = [
            $row->id,
            $this->dateTime($row->created_at),
            $row->buyer?->full_name,
            $row->buyer?->phone_number,
            $this->enumValue($row->status),
            $this->enumValue($row->source),
            $this->enumValue($row->channel),
            $row->items_count,
            $this->enumValue($row->latestPayment?->method),
            $this->enumValue($row->latestPayment?->status),
        ];

        if ($this->includePrices) {
            array_push(
                $values,
                $this->money($row->subtotal),
                $this->money($row->discount_amount),
                $this->money($row->tax_amount),
                $this->money($row->total_amount),
                $this->money($row->fee?->owedAmount() ?? 0),
            );
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return $this->includePrices ? array_fill_keys(['K', 'L', 'M', 'N', 'O'], self::MONEY_FORMAT) : [];
    }
}
