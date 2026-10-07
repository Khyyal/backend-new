<?php

namespace Modules\Services\Services;

use Illuminate\Support\Collection;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\ServiceTypeTerm;

class ServiceTypeTermService
{
    /**
     * @return Collection<int, ServiceTypeTerm> one entry per ServiceType case; unsaved when none defined yet
     */
    public function listForCenter(Center $center): Collection
    {
        $existing = ServiceTypeTerm::query()
            ->where('center_id', $center->getKey())
            ->get()
            ->keyBy(fn (ServiceTypeTerm $term) => $term->type->value);

        return collect(ServiceType::cases())
            ->map(fn (ServiceType $type) => $existing->get($type->value) ?? new ServiceTypeTerm(['type' => $type]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Center $center, ServiceType $type, array $data): ServiceTypeTerm
    {
        $terms = [
            'ar' => $data['terms']['ar'],
        ];
        if (isset($data['terms']['en']) && $data['terms']['en'] !== null) {
            $terms['en'] = $data['terms']['en'];
        }

        return ServiceTypeTerm::query()->updateOrCreate(
            ['center_id' => $center->getKey(), 'type' => $type->value],
            ['terms' => $terms]
        );
    }
}
