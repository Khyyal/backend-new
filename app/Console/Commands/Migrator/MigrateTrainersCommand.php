<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacySchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Services\Models\Trainer;

#[Signature('migrator:trainers {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy trainers (all staff types) with their schedules, keeping their ids, and link them to their center users')]
class MigrateTrainersCommand extends LegacyMigrator
{
    protected function migrate(): void
    {
        $centerIds = DB::table('centers')->pluck('id')->flip();
        $existing = DB::table('trainers')->pluck('id')->flip();
        $type = (new Trainer)->getMorphClass();

        $this->chunkLegacy('trainers', function (Collection $trainers) use ($centerIds, $existing, $type): void {
            foreach ($trainers as $trainer) {
                if (! $centerIds->has($trainer->barn_id)) {
                    $this->addWarning("Trainer #{$trainer->id}: barn #{$trainer->barn_id} was not migrated; skipped.");

                    continue;
                }

                if ($existing->has($trainer->id)) {
                    $this->tally('trainers already present');

                    continue;
                }

                DB::table('trainers')->insert([
                    'id' => $trainer->id,
                    'center_id' => $trainer->barn_id,
                    'name' => $trainer->name,
                    'max_tickets_per_hour' => $trainer->max_tickets_per_hour > 0 ? $trainer->max_tickets_per_hour : null,
                    'max_tickets_per_hour_by_day' => null,
                    'created_at' => $trainer->created_at,
                    'updated_at' => $trainer->updated_at,
                    'deleted_at' => $trainer->deleted_at,
                ]);

                $windows = LegacySchedule::windows($trainer->hours);

                if (LegacySchedule::wrapsPastMidnight($windows)) {
                    $this->addWarning("Trainer #{$trainer->id}: hours run past midnight; the early-morning part is kept on the same day.");
                }

                $rows = LegacySchedule::scheduleRows($type, $trainer->id, LegacySchedule::days($trainer->days), $windows);
                DB::table('schedules')->insert($rows);

                if ($trainer->status === 'inactive') {
                    $this->addWarning("Trainer #{$trainer->id} was inactive in the legacy app; migrated as a regular trainer.");
                }

                $this->tally('trainers');
                $this->tally('trainer schedule rows', count($rows));

                if ($trainer->barn_manager_id !== null) {
                    $this->linkCenterUser($trainer);
                }
            }
        });
    }

    /**
     * Links the trainer to its legacy barn manager's assignment in the same
     * center (`center_user_assignment.trainer_id`). Primary users never carry
     * a trainer, and a center can link a trainer to one user only.
     */
    private function linkCenterUser(object $trainer): void
    {
        $userId = $this->resolveId(MigrateCenterUsersCommand::ENTITY, $trainer->barn_manager_id);

        $assignment = DB::table('center_user_assignment')
            ->where('center_id', $trainer->barn_id)
            ->where('user_id', $userId)
            ->first();

        if ($assignment === null) {
            $this->addWarning("Trainer #{$trainer->id}: manager #{$trainer->barn_manager_id} has no assignment in center #{$trainer->barn_id}; not linked.");

            return;
        }

        if ($assignment->is_primary || $assignment->trainer_id !== null) {
            $this->addWarning("Trainer #{$trainer->id}: center user #{$userId} is primary or already linked to a trainer; not linked.");

            return;
        }

        DB::table('center_user_assignment')->where('id', $assignment->id)->update(['trainer_id' => $trainer->id]);
        $this->tally('center users linked to trainers');
    }
}
