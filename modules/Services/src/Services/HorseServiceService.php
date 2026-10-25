<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\HorseService;
use Modules\Support\Enums\ActivationStatus;

class HorseServiceService
{
    public function create(array $data): HorseService
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

            $horseService = HorseService::query()->create();

            $service = $horseService->service()->create([
                'name' => $name,
                'description' => $description,
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::HorseService,
            ]);

            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'quantity' => null,
                    'unit' => PriceOptionUnit::OPTION,
                ]);
            }

            $horseService->load(['service.priceOptions']);

            return $horseService;
        });
    }

    public function update(HorseService $horseService, array $data): HorseService
    {
        return DB::transaction(function () use ($horseService, $data) {
            $service = $horseService->service()
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

            $service->priceOptions()->forceDelete();
            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'quantity' => null,
                    'unit' => PriceOptionUnit::OPTION,
                ]);
            }

            $horseService = $horseService->fresh();
            $horseService->load(['service.priceOptions']);

            return $horseService;
        });
    }

    public function delete(HorseService $horseService): void
    {
        DB::transaction(function () use ($horseService) {
            $service = $horseService->service()
                ->lockForUpdate()
                ->firstOrFail();

            $service->priceOptions()->delete();
            $horseService->delete();
            $service->delete();
        });
    }
}
