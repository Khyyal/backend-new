<?php

namespace Modules\Exports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Exports\Enums\ExportStatus;
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
