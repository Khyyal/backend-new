<?php

namespace App\Console\Commands\Migrator;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Base for the commands that copy data from the old Khyyal database (the
 * `legacy` connection) into the new structure. Each migrator is idempotent:
 * rows are upserted by their legacy id, so it can be re-run safely.
 */
abstract class LegacyMigrator extends Command
{
    /**
     * Signature options shared by migrators that copy legacy gallery images
     * (legacy `images` table, files on S3) into media collections.
     */
    public const MEDIA_OPTIONS = '
    {--images-disk=s3 : Disk holding the legacy gallery files (images table)}
    {--images-prefix=images : Folder on that disk; files are read from <prefix>/<filename>}
    {--media-disk=s3 : Disk the migrated media is stored on}
    {--skip-media : Migrate the rows only, without media}';

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

    /**
     * Copies legacy gallery rows (`images` table: id, filename, blur_hash) to
     * the model's collection, in the given order. Originals stay on the source
     * disk; images already copied (by `legacy_image_id`) are skipped.
     *
     * @param  Collection<int, object>  $images
     */
    protected function copyLegacyImages(HasMedia&Model $model, Collection $images, string $collection, string $label): void
    {
        $sourceDisk = (string) $this->option('images-disk');
        $disk = Storage::disk($sourceDisk);
        $prefix = trim((string) $this->option('images-prefix'), '/');

        $attached = $model->getMedia($collection)
            ->map(fn (Media $media): mixed => $media->getCustomProperty('legacy_image_id'))
            ->filter()
            ->flip();

        foreach ($images as $image) {
            if ($attached->has($image->id)) {
                $this->tally('images already present');

                continue;
            }

            $key = ltrim($prefix.'/'.$image->filename, '/');

            if (! $disk->exists($key)) {
                $this->addWarning("{$label}: image #{$image->id} ({$key}) not found on disk '{$sourceDisk}'; skipped.");
                $this->tally('images missing');

                continue;
            }

            try {
                $model->addMediaFromDisk($key, $sourceDisk)
                    ->preservingOriginal()
                    ->withCustomProperties(['legacy_image_id' => $image->id, 'blur_hash' => $image->blur_hash])
                    ->toMediaCollection($collection, (string) $this->option('media-disk'));

                $this->tally('images attached');
            } catch (Throwable $exception) {
                $this->addWarning("{$label}: image #{$image->id} not attached ({$exception->getMessage()}).");
            }
        }
    }

    /**
     * Legacy money (SAR as double/decimal) in integer halalas.
     */
    protected function halalas(mixed $amount): int
    {
        return max(0, (int) round(((float) $amount) * 100));
    }

    /**
     * The new id recorded for a legacy id, or null when it was never mapped.
     */
    protected function mappedId(string $entity, int $legacyId): ?int
    {
        $id = DB::table('legacy_id_maps')
            ->where('entity', $entity)
            ->where('legacy_id', $legacyId)
            ->value('new_id');

        return $id === null ? null : (int) $id;
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
