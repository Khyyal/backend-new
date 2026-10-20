<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Models\Service;

class ServicePeriodService
{
    public function findServiceForCenter(Center $center, int|string $id): Service
    {
        return Service::query()
            ->whereKey($id)
            ->where('center_id', $center->getKey())
            ->firstOrFail();
    }

    /**
     * Replaces the service's periods. A gender that is missing or null is cleared.
     *
     * @param  array<string, array{days: array<int, int>, hours: array<int, string>}|null>  $data
     */
    public function sync(Service $service, array $data): Service
    {
        DB::transaction(function () use ($service, $data): void {
            $service->periods()->delete();

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
                            'end_time' => $end,
                        ];
                    }
                }
            }

            $service->periods()->createMany($rows);
        });

        return $service->load('periods');
    }
}
