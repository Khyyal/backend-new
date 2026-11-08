<?php

namespace Modules\Exports\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exports\Enums\ExportStatus;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Exports\QueuedExport;
use Modules\Exports\Jobs\MarkExportCompleted;
use Modules\Exports\Models\Export;
use Modules\Exports\Notifications\ExportFailedNotification;
use Modules\Exports\Notifications\ExportReadyNotification;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Records an export, queues it through Laravel Excel and tells the requester
 * when the file is ready or failed. Status only moves pending -> completed
 * or pending -> failed, through conditional updates, so concurrent chunk
 * failures notify once.
 */
class ExportService
{
    public function __construct(private readonly NotificationServiceContract $notifications) {}

    /**
     * Queue `$export` for `$requester`; the caller does not wait for the file.
     *
     * @throws TooManyRequestsHttpException when the same export is still being built.
     */
    public function start(Model $requester, ExportType $type, QueuedExport $export, ?int $centerId = null): Export
    {
        $this->ensureNoneInProgress($requester, $type, $centerId);

        $fileName = sprintf('%s-%s.xlsx', $type->fileNamePrefix(), now()->format('Y-m-d-His'));

        $record = Export::query()->create([
            'requester_type' => $requester->getMorphClass(),
            'requester_id' => $requester->getKey(),
            'center_id' => $centerId,
            'type' => $type,
            'filters' => $export->filters,
            'status' => ExportStatus::Pending,
            'disk' => (string) config('exports.disk'),
            'path' => 'exports/'.Str::ulid().'/'.$fileName,
            'file_name' => $fileName,
            'rows_count' => $export->query()->count(),
        ]);

        $export->exportId = $record->id;

        $export->queue($record->path, $record->disk)
            ->allOnQueue((string) config('exports.queue'))
            ->chain([new MarkExportCompleted($record->id)]);

        return $record->refresh();
    }

    public function markCompleted(int $exportId): void
    {
        $updated = Export::query()
            ->whereKey($exportId)
            ->where('status', ExportStatus::Pending)
            ->update([
                'status' => ExportStatus::Completed,
                'completed_at' => now(),
                'expires_at' => now()->addDays((int) config('exports.retention_days')),
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $this->notifyRequester($exportId, ExportReadyNotification::class);
        }
    }

    public function markFailed(int $exportId, Throwable $exception): void
    {
        $updated = Export::query()
            ->whereKey($exportId)
            ->where('status', ExportStatus::Pending)
            ->update([
                'status' => ExportStatus::Failed,
                'error' => Str::limit($exception->getMessage(), 1000),
                'failed_at' => now(),
                'expires_at' => now()->addDays((int) config('exports.retention_days')),
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $this->notifyRequester($exportId, ExportFailedNotification::class);
        }
    }

    /**
     * Stream a finished export.
     *
     * @throws ConflictHttpException when the export is not completed.
     * @throws GoneHttpException when it expired or its file is missing.
     */
    public function download(Export $export): StreamedResponse
    {
        if ($export->status !== ExportStatus::Completed) {
            throw new ConflictHttpException(__('exports::messages.not_ready'));
        }

        $disk = Storage::disk($export->disk);

        if ($export->isExpired() || ! $disk->exists($export->path)) {
            throw new GoneHttpException(__('exports::messages.expired'));
        }

        return $disk->download($export->path, $export->file_name);
    }

    /**
     * Delete expired exports and their files, and pending exports whose
     * worker never finished within the retention period.
     */
    public function pruneExpired(): int
    {
        $pruned = 0;

        Export::query()
            ->where(fn ($query) => $query
                ->where('expires_at', '<', now())
                ->orWhere(fn ($pending) => $pending
                    ->where('status', ExportStatus::Pending)
                    ->where('created_at', '<', now()->subDays((int) config('exports.retention_days')))))
            ->chunkById(200, function ($exports) use (&$pruned): void {
                foreach ($exports as $export) {
                    Storage::disk($export->disk)->deleteDirectory(dirname($export->path));
                    $export->delete();
                    $pruned++;
                }
            });

        return $pruned;
    }

    private function ensureNoneInProgress(Model $requester, ExportType $type, ?int $centerId): void
    {
        $inProgress = Export::query()
            ->where('requester_type', $requester->getMorphClass())
            ->where('requester_id', $requester->getKey())
            ->where('type', $type)
            ->where('center_id', $centerId)
            ->where('status', ExportStatus::Pending)
            ->where('created_at', '>=', now()->subMinutes((int) config('exports.stale_after_minutes')))
            ->exists();

        if ($inProgress) {
            throw new TooManyRequestsHttpException(message: __('exports::messages.in_progress'));
        }
    }

    /**
     * @param  class-string<ExportReadyNotification|ExportFailedNotification>  $notification
     */
    private function notifyRequester(int $exportId, string $notification): void
    {
        $export = Export::query()->with('requester')->find($exportId);

        if ($export?->requester !== null) {
            $this->notifications->send($export->requester, new $notification($export));
        }
    }
}
