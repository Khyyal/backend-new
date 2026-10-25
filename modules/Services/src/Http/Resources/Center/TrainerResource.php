<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;

/**
 * @property Trainer $resource
 */
class TrainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $schedules = $this->resource->schedules->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']]);
        $firstDay = $schedules->first()?->day_of_week;

        return [
            'id' => $this->resource->id,
            'center_id' => $this->resource->center_id,
            'name' => $this->resource->name,
            'max_tickets_per_hour' => $this->resource->max_tickets_per_hour,
            'days' => $schedules->pluck('day_of_week')->unique()->values(),
            'hours' => $schedules->where('day_of_week', $firstDay)
                ->flatMap(fn ($slot) => [substr($slot->start_time, 0, 5), substr($slot->end_time, 0, 5)])
                ->values(),
            'training_services' => $this->resource->trainingServices
                ->sortBy('id')
                ->map(fn (TrainingService $trainingService) => [
                    'id' => $trainingService->id,
                    'name' => $trainingService->service?->getTranslations('name'),
                ])->values(),
        ];
    }
}
