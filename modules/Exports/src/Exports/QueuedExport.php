<?php

namespace Modules\Exports\Exports;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Modules\Exports\Services\ExportService;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Base for every background spreadsheet export.
 *
 * Laravel Excel serializes the export into its chunk jobs, so it only holds
 * plain values: the `Export` row id, the validated filters and whatever ids
 * the subclass needs to rebuild its query in the worker.
 */
abstract class QueuedExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public const string MONEY_FORMAT = '#,##0.00';

    public const string DATE_TIME_FORMAT = 'Y-m-d H:i';

    public ?int $exportId = null;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(public readonly array $filters = []) {}

    /**
     * Bold heading row.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [];
    }

    /**
     * Called by Laravel Excel when a chunk job fails.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->exportId !== null) {
            app(ExportService::class)->markFailed($this->exportId, $exception);
        }
    }

    /**
     * Halalas as SAR for a money cell formatted with {@see self::MONEY_FORMAT}.
     */
    protected function money(?int $halalas): ?float
    {
        return $halalas === null ? null : $halalas / 100;
    }

    /**
     * A date cell; accepts raw database strings from query-builder rows.
     */
    protected function dateTime(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return ($value instanceof DateTimeInterface ? $value : Carbon::parse($value))->format(self::DATE_TIME_FORMAT);
    }

    protected function enumValue(?BackedEnum $value): string|int|null
    {
        return $value?->value;
    }
}
