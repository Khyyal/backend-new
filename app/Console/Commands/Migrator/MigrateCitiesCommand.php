<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('migrator:cities {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy cities into the cities table, keeping their ids')]
class MigrateCitiesCommand extends LegacyMigrator
{
    protected function migrate(): void
    {
        $this->chunkLegacy('cities', function (Collection $cities): void {
            $rows = $cities->map(function (object $city): array {
                if ($city->lat === null || $city->lng === null) {
                    $this->addWarning("City #{$city->id} has no coordinates; stored as 0,0.");
                }

                return [
                    'id' => $city->id,
                    'name' => json_encode(['ar' => $city->name_ar, 'en' => $city->name_en], JSON_UNESCAPED_UNICODE),
                    'lat' => $city->lat ?? 0,
                    'lng' => $city->lng ?? 0,
                    'radius' => $city->radius ?? 0,
                    'created_at' => $city->created_at,
                    'updated_at' => $city->updated_at,
                ];
            })->all();

            DB::table('cities')->upsert($rows, ['id'], ['name', 'lat', 'lng', 'radius', 'updated_at']);

            $this->tally('cities', count($rows));
        });
    }
}
