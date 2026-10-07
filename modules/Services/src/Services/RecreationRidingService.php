<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\RecreationalRiding;
use Modules\Support\Enums\ActivationStatus;

class RecreationRidingService
{
    public function create(array $data): RecreationalRiding
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

            $riding = RecreationalRiding::query()->create([
                'max_tickets_per_hour' => $data['max_tickets_per_hour'] ?? null,
            ]);

            $service = $riding->service()->create([
                'name' => $name,
                'description' => $description,
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::RecreationRiding,
            ]);

            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => null,
                    'price' => $option['price'],
                    'quantity' => $option['duration'],
                    'unit' => PriceOptionUnit::MINUTE,
                ]);
            }

            $hours = $data['hours'];
            $slotCount = (int) (count($hours) / 2);
            foreach ($data['days'] as $day) {
                for ($i = 0; $i < $slotCount; $i++) {
                    $startIndex = $i * 2;
                    $endIndex = $i * 2 + 1;
                    $riding->schedules()->create([
                        'day_of_week' => $day,
                        'start_time' => $hours[$startIndex],
                        'end_time' => $hours[$endIndex],
                    ]);
                }
            }

            $riding->load(['service.priceOptions', 'schedules']);

            return $riding;
        });
    }

    public function update(RecreationalRiding $riding, array $data): RecreationalRiding
    {
        return DB::transaction(function () use ($riding, $data) {
            $service = $riding->service()
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

            $riding->update([
                'max_tickets_per_hour' => $data['max_tickets_per_hour'] ?? null,
            ]);

            $service->priceOptions()->forceDelete();
            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => null,
                    'price' => $option['price'],
                    'quantity' => $option['duration'],
                    'unit' => PriceOptionUnit::MINUTE,
                ]);
            }

            $riding->schedules()->forceDelete();
            $hours = $data['hours'];
            $slotCount = (int) (count($hours) / 2);
            foreach ($data['days'] as $day) {
                for ($i = 0; $i < $slotCount; $i++) {
                    $startIndex = $i * 2;
                    $endIndex = $i * 2 + 1;
                    $riding->schedules()->create([
                        'day_of_week' => $day,
                        'start_time' => $hours[$startIndex],
                        'end_time' => $hours[$endIndex],
                    ]);
                }
            }

            $riding = $riding->fresh();
            $riding->load(['service.priceOptions', 'schedules']);

            return $riding;
        });
    }

    public function delete(RecreationalRiding $riding): void
    {
        DB::transaction(function () use ($riding) {
            $service = $riding->service()
                ->lockForUpdate()
                ->firstOrFail();

            $riding->schedules()->delete();
            $service->priceOptions()->delete();
            $riding->delete();
            $service->delete();
        });
    }
}
