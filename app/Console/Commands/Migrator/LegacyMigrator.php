<?php

namespace App\Console\Commands\Migrator;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base for the commands that copy data from the old Khyyal database (the
 * `legacy` connection) into the new structure. Each migrator is idempotent:
 * rows are upserted by their legacy id, so it can be re-run safely.
 */
abstract class LegacyMigrator extends Command
{
    /** @var array<int, string> */
    protected array $warnings = [];

    /** @var array<string, int> */
    protected array $stats = [];

    /**
     * Copies the legacy rows into the new tables.
     */
    abstract protected function migrate(): void;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            DB::beginTransaction();

            $this->migrate();

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $this->afterCommit($dryRun);
        $this->printSummary($dryRun);

        return self::SUCCESS;
    }

    /**
     * Work that must only run once the data is committed (cache flushes, media).
     */
    protected function afterCommit(bool $dryRun): void {}

    protected function legacy(): Connection
    {
        return DB::connection('legacy');
    }

    /**
     * Iterates a legacy table in id order, 500 rows at a time.
     *
     * @param  Closure(Collection<int, object>): void  $callback
     */
    protected function chunkLegacy(string $table, Closure $callback, string $column = 'id'): void
    {
        $this->legacy()->table($table)->orderBy($column)->chunkById(500, $callback, $column);
    }

    protected function mapId(string $entity, int $legacyId, int $newId): void
    {
        DB::table('legacy_id_maps')->upsert(
            [['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]],
            ['entity', 'legacy_id'],
            ['new_id', 'updated_at'],
        );
    }

    /**
     * The new id for a legacy id: the mapped one when the row was merged or
     * re-keyed, otherwise the legacy id itself (ids are kept by default).
     */
    protected function resolveId(string $entity, int $legacyId): int
    {
        return (int) (DB::table('legacy_id_maps')
            ->where('entity', $entity)
            ->where('legacy_id', $legacyId)
            ->value('new_id') ?? $legacyId);
    }

    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    protected function tally(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }

    private function printSummary(bool $dryRun): void
    {
        $this->info(class_basename($this).($dryRun ? ' (dry run, rolled back)' : ''));

        $this->table(['Metric', 'Count'], collect($this->stats)->map(fn (int $count, string $key): array => [$key, $count])->values()->all());

        foreach ($this->warnings as $warning) {
            $this->warn($warning);
        }
    }
}
