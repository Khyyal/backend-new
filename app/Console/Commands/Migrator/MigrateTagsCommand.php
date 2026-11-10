<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('migrator:tags {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy barn features into tags (keeping their ids) and link them to centers')]
class MigrateTagsCommand extends LegacyMigrator
{
    protected function migrate(): void
    {
        $this->migrateTags();
        $this->migrateCenterTags();
    }

    /**
     * Legacy `features` (translatable JSON names) are the new `tags`.
     */
    private function migrateTags(): void
    {
        $this->chunkLegacy('features', function (Collection $features): void {
            $rows = [];

            foreach ($features as $feature) {
                $name = json_decode((string) $feature->name, true);

                if (! is_array($name) || blank($name['ar'] ?? $name['en'] ?? null)) {
                    $this->addWarning("Feature #{$feature->id}: name is not a translatable JSON object; skipped.");

                    continue;
                }

                $rows[] = ['id' => $feature->id, 'name' => json_encode($name, JSON_UNESCAPED_UNICODE)];
            }

            if ($rows !== []) {
                DB::table('tags')->upsert($rows, ['id'], ['name']);
            }

            $this->tally('tags', count($rows));
        });
    }

    /**
     * `barn_features` → `tag_center`, for migrated centers and tags only.
     */
    private function migrateCenterTags(): void
    {
        $centerIds = DB::table('centers')->pluck('id')->flip();
        $tagIds = DB::table('tags')->pluck('id')->flip();

        $this->chunkLegacy('barn_features', function (Collection $links) use ($centerIds, $tagIds): void {
            $rows = [];

            foreach ($links as $link) {
                if (! $centerIds->has($link->barn_id) || ! $tagIds->has($link->feature_id)) {
                    $this->tally('tag links skipped (no center or tag)');

                    continue;
                }

                $rows[] = ['tag_id' => $link->feature_id, 'center_id' => $link->barn_id];
            }

            if ($rows !== []) {
                $this->tally('tag links added', DB::table('tag_center')->insertOrIgnore($rows));
            }
        });
    }
}
