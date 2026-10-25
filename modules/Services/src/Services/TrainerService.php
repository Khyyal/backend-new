<?php

namespace Modules\Services\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Services\Models\Trainer;

class TrainerService
{
    private const RELATIONS = ['schedules', 'trainingServices.service'];

    public function list(Center $center): LengthAwarePaginator
    {
        return Trainer::query()
            ->where('center_id', $center->getKey())
            ->with(self::RELATIONS)
            ->latest('id')
            ->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Center $center, array $data): Trainer
    {
        return DB::transaction(function () use ($center, $data) {
            $trainer = Trainer::query()->create([
                'center_id' => $center->getKey(),
                'name' => $data['name'],
                'max_tickets_per_hour' => $data['max_tickets_per_hour'] ?? null,
            ]);

            $this->syncSchedules($trainer, $data);

            if (array_key_exists('training_service_ids', $data)) {
                $trainer->trainingServices()->sync($data['training_service_ids']);
            }

            return $trainer->load(self::RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Trainer $trainer, array $data): Trainer
    {
        return DB::transaction(function () use ($trainer, $data) {
            $trainer->update([
                'name' => $data['name'],
                'max_tickets_per_hour' => $data['max_tickets_per_hour'] ?? null,
            ]);

            $trainer->schedules()->forceDelete();
            $this->syncSchedules($trainer, $data);

            if (array_key_exists('training_service_ids', $data)) {
                $trainer->trainingServices()->sync($data['training_service_ids']);
            }

            return $trainer->unsetRelations()->load(self::RELATIONS);
        });
    }

    public function delete(Trainer $trainer): void
    {
        DB::transaction(function () use ($trainer) {
            $trainer->trainingServices()->detach();
            $trainer->schedules()->delete();
            $trainer->delete();
        });
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findForCenter(Center $center, int|string $id): Trainer
    {
        return Trainer::query()
            ->whereKey($id)
            ->where('center_id', $center->getKey())
            ->with(self::RELATIONS)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncSchedules(Trainer $trainer, array $data): void
    {
        $hours = $data['hours'];
        $slotCount = (int) (count($hours) / 2);

        foreach ($data['days'] as $day) {
            for ($i = 0; $i < $slotCount; $i++) {
                $trainer->schedules()->create([
                    'day_of_week' => $day,
                    'start_time' => $hours[$i * 2],
                    'end_time' => $hours[$i * 2 + 1],
                ]);
            }
        }
    }
}
