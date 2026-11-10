<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacySchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Enums\ServiceType;

#[Signature('migrator:center-settings {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy gender working hours into center periods and service policies into service type terms')]
class MigrateCenterSettingsCommand extends LegacyMigrator
{
    /** @var array<string, ServiceType> Legacy policy service type → new type (stabling is not migrated). */
    private const POLICY_TYPES = [
        'free_riding' => ServiceType::RecreationRiding,
        'training' => ServiceType::Training,
        'visit' => ServiceType::Visit,
        'event' => ServiceType::Event,
        'resort' => ServiceType::Resort,
        'membership' => ServiceType::Membership,
    ];

    /** @var Collection<int, int> */
    private Collection $centerIds;

    protected function migrate(): void
    {
        $this->centerIds = DB::table('centers')->pluck('id')->flip();

        $this->migratePeriods();
        $this->migratePolicies();
    }

    /**
     * `custom_working_hours` (days + one-hour slots per gender) → `center_periods`.
     * A center's periods are replaced only when it has legacy rows.
     */
    private function migratePeriods(): void
    {
        $byCenter = $this->legacy()->table('custom_working_hours')->orderBy('id')->get()->groupBy('barn_id');

        foreach ($byCenter as $centerId => $rows) {
            if (! $this->centerIds->has($centerId)) {
                $this->addWarning("Working hours of barn #{$centerId}: barn was not migrated; skipped.");

                continue;
            }

            $periods = [];

            foreach ($rows as $row) {
                $gender = PeriodGender::tryFrom(strtolower(trim((string) $row->gender)));

                if ($gender === null) {
                    $this->addWarning("Working hours #{$row->id}: unknown gender '{$row->gender}'; skipped.");

                    continue;
                }

                $windows = LegacySchedule::windows($row->hours);

                foreach (LegacySchedule::days($row->days) as $day) {
                    foreach ($windows as [$start, $end]) {
                        $periods["{$gender->value}-{$day}-{$start}"] = [
                            'center_id' => $centerId,
                            'gender' => $gender->value,
                            'day_of_week' => $day,
                            'start_time' => $start,
                            'end_time' => $end,
                        ];
                    }
                }
            }

            DB::table('center_periods')->where('center_id', $centerId)->delete();
            DB::table('center_periods')->insert(array_values($periods));

            $this->tally('centers with periods');
            $this->tally('center period rows', count($periods));
        }
    }

    /**
     * `service_policies` (per barn and service type) → `service_type_terms`.
     * When a center has several policies for one type, the latest wins.
     */
    private function migratePolicies(): void
    {
        $policies = $this->legacy()->table('service_policies')->orderBy('updated_at')->orderBy('id')->get();
        $terms = [];

        foreach ($policies as $policy) {
            $type = self::POLICY_TYPES[$policy->service_type] ?? null;

            if ($type === null) {
                $this->tally("policies skipped ({$policy->service_type})");

                continue;
            }

            if (! $this->centerIds->has($policy->barn_id) || blank($policy->policy)) {
                $this->tally('policies skipped (no center or empty)');

                continue;
            }

            $terms["{$policy->barn_id}-{$type->value}"] = [
                'center_id' => $policy->barn_id,
                'type' => $type->value,
                'terms' => json_encode(['ar' => $policy->policy], JSON_UNESCAPED_UNICODE),
                'created_at' => $policy->created_at,
                'updated_at' => $policy->updated_at,
            ];
        }

        foreach (array_chunk(array_values($terms), 500) as $chunk) {
            DB::table('service_type_terms')->upsert($chunk, ['center_id', 'type'], ['terms', 'updated_at']);
        }

        $this->tally('service type terms', count($terms));
    }
}
