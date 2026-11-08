<?php

namespace Modules\Exports\Console;

use Illuminate\Console\Command;
use Modules\Exports\Services\ExportService;

class PruneExpiredExportsCommand extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired spreadsheet exports and their files';

    public function handle(ExportService $exports): int
    {
        $this->info("Pruned {$exports->pruneExpired()} export(s).");

        return self::SUCCESS;
    }
}
