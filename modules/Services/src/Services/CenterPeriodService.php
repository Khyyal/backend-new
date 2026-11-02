<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Models\Schedule;

class CenterPeriodService
{
    /**
     * Replaces the center's periods. A gender that is missing or null is cleared.
     *
     * @param  array<string, array{days: array<int, int>, hours: array<int, string>}|null>  $data
     */
    public function sync(Center $center, array $data): Center
    {
        DB::transaction(function () use ($center, $data): void {
            $center->periods()->delete();

            $rows = [];
            foreach (PeriodGender::cases() as $gender) {
                $period = $data[$gender->value] ?? null;

                if ($period === null) {
                    continue;
                }

                foreach ($period['days'] as $day) {
                    foreach (array_chunk($period['hours'], 2) as [$start, $end]) {
                        $rows[] = [
                            'gender' => $gender->value,
                            'day_of_week' => $day,
                            'start_time' => $start,
                            'end_time' => Schedule::endTime($end),
                        ];
                    }
                }
            }

            $center->periods()->createMany($rows);
        });

        return $center->load('periods');
    }
}
