<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Event;
use Modules\Support\Enums\ActivationStatus;

class EventService
{
    public function create(array $data): Event
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

            $event = Event::query()->create([
                'occurrence_type' => $data['occurrence_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'open_date' => $data['open_date'],
                'close_date' => $data['close_date'],
                'max_tickets_per_day' => $data['max_tickets_per_day'],
            ]);

            $service = $event->service()->create([
                'name' => $name,
                'description' => $description,
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::Event,
            ]);

            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'quantity' => null,
                    'unit' => PriceOptionUnit::OPTION,
                ]);
            }

            $event->load(['service.priceOptions']);

            return $event;
        });
    }

    public function update(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data) {
            $service = $event->service()
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

            $event->update([
                'occurrence_type' => $data['occurrence_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'open_date' => $data['open_date'],
                'close_date' => $data['close_date'],
                'max_tickets_per_day' => $data['max_tickets_per_day'],
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

            $event = $event->fresh();
            $event->load(['service.priceOptions']);

            return $event;
        });
    }

    public function delete(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $service = $event->service()
                ->lockForUpdate()
                ->firstOrFail();

            $service->priceOptions()->delete();
            $event->delete();
            $service->delete();
        });
    }
}
