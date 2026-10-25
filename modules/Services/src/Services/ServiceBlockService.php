<?php

namespace Modules\Services\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Models\ServiceBlock;

class ServiceBlockService
{
    public function list(Center $center, ?int $serviceId = null): LengthAwarePaginator
    {
        return ServiceBlock::query()
            ->where('center_id', $center->getKey())
            ->when($serviceId !== null, fn (Builder $query) => $query->where('service_id', $serviceId))
            ->with('periods')
            ->latest('id')
            ->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Center $center, array $data): ServiceBlock
    {
        $block = ServiceBlock::query()->create([...Arr::except($data, ['hours']), 'center_id' => $center->getKey()]);

        $this->syncPeriods($block, $data);

        return $block->load('periods');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ServiceBlock $block, array $data): ServiceBlock
    {
        $block->update(Arr::except($data, ['hours']));
        $block->periods()->delete();

        $this->syncPeriods($block, $data);

        return $block->load('periods');
    }

    public function delete(ServiceBlock $block): void
    {
        $block->delete();
    }

    public function findForCenter(Center $center, int|string $id): ServiceBlock
    {
        return ServiceBlock::query()
            ->whereKey($id)
            ->where('center_id', $center->getKey())
            ->with('periods')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPeriods(ServiceBlock $block, array $data): void
    {
        if ($block->time_scope !== BlockTimeScope::SpecificTime) {
            return;
        }

        $hours = $data['hours'];
        $slotCount = (int) (count($hours) / 2);
        for ($i = 0; $i < $slotCount; $i++) {
            $block->periods()->create([
                'start_time' => $hours[$i * 2],
                'end_time' => $hours[$i * 2 + 1],
            ]);
        }
    }
}
