<?php

namespace Modules\Exports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Exports\Enums\ExportStatus;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Models\Export;
use Modules\Support\Models\SystemUser;

/**
 * @mixin Export
 */
class ExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Export ID.', example: 14)]
    #[ResponseField('type', 'string', 'What is exported.', enum: ExportType::class, example: 'center_purchases')]
    #[ResponseField('status', 'string', '`pending` while generating, then `completed` or `failed`.', enum: ExportStatus::class, example: 'pending')]
    #[ResponseField('center_id', 'integer', 'Center the export belongs to; null for platform-wide exports.', nullable: true, example: 8)]
    #[ResponseField('file_name', 'string', 'Name of the `.xlsx` file once generated.', nullable: true, example: 'purchases-2026-05-20.xlsx')]
    #[ResponseField('rows_count', 'integer', 'Exported rows once completed.', nullable: true, example: 128)]
    #[ResponseField('filters', 'object', 'Filters the export was requested with (empty object when none).', example: ['status' => 'confirmed', 'from' => '2026-05-01'])]
    #[ResponseField('created_at', 'string', 'Request time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('completed_at', 'string', 'Completion time (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('failed_at', 'string', 'Failure time (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('expires_at', 'string', 'When the file is deleted (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('download_url', 'string', 'Authenticated download link (send your bearer token); null until completed or once expired.', nullable: true, example: 'No-example')]
    public function toArray(Request $request): array
    {
        /** @var Export $export */
        $export = $this->resource;

        return [
            'id' => $export->id,
            'type' => $export->type->value,
            'status' => $export->status->value,
            'center_id' => $export->center_id,
            'file_name' => $export->file_name,
            'rows_count' => $export->rows_count,
            'filters' => (object) ($export->filters ?? []),
            'created_at' => $export->created_at->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'failed_at' => $export->failed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            /** Authenticated download link, `null` until completed or once expired. */
            'download_url' => $this->downloadUrl($export),
        ];
    }

    private function downloadUrl(Export $export): ?string
    {
        if ($export->status !== ExportStatus::Completed || $export->isExpired()) {
            return null;
        }

        if ($export->requester_type === (new SystemUser)->getMorphClass()) {
            return route('admin.exports.download', $export);
        }

        return route('centers.exports.download', ['center' => $export->center_id, 'export' => $export]);
    }
}
