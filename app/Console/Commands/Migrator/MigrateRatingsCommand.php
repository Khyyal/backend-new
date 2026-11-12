<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;

#[Signature('migrator:ratings {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy reservation ratings into center ratings, keeping each client\'s latest rating per center')]
class MigrateRatingsCommand extends LegacyMigrator
{
    /**
     * Legacy ratings sit on a reservation (with its barn); the new ones sit on
     * the center, one per client. Keyed by "center|client", so the latest
     * legacy rating of a client for a center wins.
     *
     * @var array<string, array{stars: int, comment: ?string, rateable_type: string, rateable_id: int, rater_type: string, rater_id: int, created_at: ?string, updated_at: ?string}>
     */
    private array $ratings = [];

    protected function migrate(): void
    {
        $this->ratings = [];
        $centerIds = DB::table('centers')->pluck('id')->flip();
        $centerMorph = (new Center)->getMorphClass();
        $clientMorph = (new Client)->getMorphClass();

        $this->chunkLegacy('ratings', function (Collection $rows) use ($centerIds, $centerMorph, $clientMorph): void {
            foreach ($rows as $row) {
                if (! $centerIds->has($row->barn_id)) {
                    $this->tally('ratings skipped (center not migrated)');

                    continue;
                }

                $clientId = $this->migratedClientId($row->client_id === null ? null : (int) $row->client_id);

                if ($clientId === null) {
                    $this->tally('ratings skipped (client not migrated)');

                    continue;
                }

                $key = "{$row->barn_id}|{$clientId}";

                if (isset($this->ratings[$key])) {
                    $this->tally('duplicate ratings collapsed (kept latest)');

                    if ((string) $row->updated_at < (string) $this->ratings[$key]['updated_at']) {
                        continue;
                    }
                }

                $this->ratings[$key] = [
                    'stars' => max(1, min(5, (int) $row->stars)),
                    'comment' => blank($row->comment) ? null : $row->comment,
                    'rateable_type' => $centerMorph,
                    'rateable_id' => (int) $row->barn_id,
                    'rater_type' => $clientMorph,
                    'rater_id' => $clientId,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
        });

        $this->writeRatings();
    }

    /**
     * Inserts the collected ratings, leaving any rating a client already gave
     * the center in the new app untouched.
     */
    private function writeRatings(): void
    {
        foreach (array_chunk(array_values($this->ratings), 500) as $rows) {
            $this->tally('ratings added', DB::table('ratings')->insertOrIgnore($rows));
        }
    }
}
