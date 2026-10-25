<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\HorseCare;
use Modules\Support\Enums\ActivationStatus;

class HorseCareService
{
    public function create(array $data): HorseCare
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

            $horseCare = HorseCare::query()->create();

            $service = $horseCare->service()->create([
                'name' => $name,
                'description' => $description,
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::HorseCare,
            ]);

            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'quantity' => null,
                    'unit' => PriceOptionUnit::OPTION,
                ]);
            }

            $horseCare->load(['service.priceOptions']);

            return $horseCare;
        });
    }

    public function update(HorseCare $horseCare, array $data): HorseCare
    {
        return DB::transaction(function () use ($horseCare, $data) {
            $service = $horseCare->service()
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

            $horseCare = $horseCare->fresh();
            $horseCare->load(['service.priceOptions']);

            return $horseCare;
        });
    }

    public function delete(HorseCare $horseCare): void
    {
        DB::transaction(function () use ($horseCare) {
            $service = $horseCare->service()
                ->lockForUpdate()
                ->firstOrFail();

            $service->priceOptions()->delete();
            $horseCare->delete();
            $service->delete();
        });
    }
}
