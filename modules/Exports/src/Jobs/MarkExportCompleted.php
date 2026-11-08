<?php

namespace Modules\Exports\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Exports\Services\ExportService;

/**
 * Chained after Laravel Excel stored the file: marks the export completed
 * and notifies the requester.
 */
class MarkExportCompleted implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $exportId) {}

    public function handle(ExportService $exports): void
    {
        $exports->markCompleted($this->exportId);
    }
}
