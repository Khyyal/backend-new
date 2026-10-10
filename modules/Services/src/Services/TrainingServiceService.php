<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\TrainingService;
use Modules\Support\Enums\ActivationStatus;

class TrainingServiceService
{
    public function create(array $data): TrainingService
    {
        return DB::transaction(function () use ($data) {
            $trainingService = TrainingService::query()->create();

            $service = $trainingService->service()->create([
                'name' => $this->translations($data['name']),
                'description' => $this->translations($data['description']),
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::Training,
            ]);

            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => null,
                    'price' => $option['price'],
                    'quantity' => $option['duration'],
                    'unit' => PriceOptionUnit::MINUTE,
                ]);
            }

            if (array_key_exists('trainer_ids', $data)) {
                $trainingService->trainers()->sync($data['trainer_ids']);
            }

            $trainingService->load(['service.priceOptions', 'trainers']);

            return $trainingService;
        });
    }

    public function update(TrainingService $trainingService, array $data): TrainingService
    {
        return DB::transaction(function () use ($trainingService, $data) {
            $service = $trainingService->service()
                ->lockForUpdate()
                ->firstOrFail();

            $service->update([
                'name' => $this->translations($data['name']),
                'description' => $this->translations($data['description']),
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

            if (array_key_exists('trainer_ids', $data)) {
                $trainingService->trainers()->sync($data['trainer_ids']);
            }

            $trainingService = $trainingService->fresh();
            $trainingService->load(['service.priceOptions', 'trainers']);

            return $trainingService;
        });
    }

    public function delete(TrainingService $trainingService): void
    {
        DB::transaction(function () use ($trainingService) {
            $service = $trainingService->service()
                ->lockForUpdate()
                ->firstOrFail();

            $trainingService->trainers()->detach();
            $service->priceOptions()->delete();
            $trainingService->delete();
            $service->delete();
        });
    }

    /**
     * @param  array{ar: string, en?: string|null}  $values
     * @return array{ar: string, en?: string}
     */
    private function translations(array $values): array
    {
        $translations = ['ar' => $values['ar']];

        if (isset($values['en'])) {
            $translations['en'] = $values['en'];
        }

        return $translations;
    }
}
