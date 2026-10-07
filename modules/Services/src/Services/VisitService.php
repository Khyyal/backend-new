<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Visit;
use Modules\Support\Enums\ActivationStatus;

class VisitService
{
    public function create(array $data): Visit
    {
        return DB::transaction(function () use ($data) {
            $name = [
                'ar' => $data['name']['ar'],
            ];
            if (isset($data['name']['en']) && $data['name']['en'] !== null) {
                $name['en'] = $data['name']['en'];
            }

            $description = [
                'ar' => $data['description']['ar'],
            ];
            if (isset($data['description']['en']) && $data['description']['en'] !== null) {
                $description['en'] = $data['description']['en'];
            }

            $visit = Visit::query()->create([
                'enter_type' => $data['enter_type'],
                'max_tickets_per_day' => $data['max_tickets_per_day'] ?? null,
            ]);

            $service = $visit->service()->create([
                'name' => $name,
                'description' => $description,
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::Visit,
            ]);

            $service->priceOptions()->create([
                'name' => null,
                'price' => $data['price'],
                'quantity' => null,
                'unit' => PriceOptionUnit::OPTION,
            ]);

            $this->createSchedules($visit, $data);

            $visit->load(['service.priceOptions', 'schedules']);

            return $visit;
        });
    }

    public function update(Visit $visit, array $data): Visit
    {
        return DB::transaction(function () use ($visit, $data) {
            $service = $visit->service()
                ->lockForUpdate()
                ->firstOrFail();

            $name = [
                'ar' => $data['name']['ar'],
            ];
            if (isset($data['name']['en']) && $data['name']['en'] !== null) {
                $name['en'] = $data['name']['en'];
            }

            $description = [
                'ar' => $data['description']['ar'],
            ];
            if (isset($data['description']['en']) && $data['description']['en'] !== null) {
                $description['en'] = $data['description']['en'];
            }

            $service->update([
                'name' => $name,
                'description' => $description,
            ]);

            $visit->update([
                'enter_type' => $data['enter_type'],
                'max_tickets_per_day' => $data['max_tickets_per_day'] ?? null,
            ]);

            $service->priceOptions()->forceDelete();
            $service->priceOptions()->create([
                'name' => null,
                'price' => $data['price'],
                'quantity' => null,
                'unit' => PriceOptionUnit::OPTION,
            ]);

            $visit->schedules()->forceDelete();
            $this->createSchedules($visit, $data);

            $visit = $visit->fresh();
            $visit->load(['service.priceOptions', 'schedules']);

            return $visit;
        });
    }

    public function delete(Visit $visit): void
    {
        DB::transaction(function () use ($visit) {
            $service = $visit->service()
                ->lockForUpdate()
                ->firstOrFail();

            $visit->schedules()->delete();
            $service->priceOptions()->delete();
            $visit->delete();
            $service->delete();
        });
    }

    private function createSchedules(Visit $visit, array $data): void
    {
        if ($data['enter_type'] === VisitEnterType::AllDay->value) {
            foreach ($data['days'] as $day) {
                $visit->schedules()->create([
                    'day_of_week' => $day,
                    'start_time' => null,
                    'end_time' => null,
                ]);
            }

            return;
        }

        $hours = $data['hours'];
        $slotCount = (int) (count($hours) / 2);
        foreach ($data['days'] as $day) {
            for ($i = 0; $i < $slotCount; $i++) {
                $startIndex = $i * 2;
                $endIndex = $i * 2 + 1;
                $visit->schedules()->create([
                    'day_of_week' => $day,
                    'start_time' => $hours[$startIndex],
                    'end_time' => $hours[$endIndex],
                ]);
            }
        }
    }
}
