<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Membership;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServicePackage;
use Modules\Support\Enums\ActivationStatus;

class MembershipService
{
    private const RELATIONS = ['service.priceOptions', 'packages.servicePackage.service', 'packages.trainer', 'visitService'];

    public function create(array $data): Membership
    {
        return DB::transaction(function () use ($data) {
            $membership = Membership::query()->create($this->attributes($data));

            $service = $membership->service()->create([
                'name' => $this->translations($data['name']),
                'description' => $this->translations($data['description']),
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::Membership,
            ]);

            $this->syncPrice($service, $data['price']);
            $this->syncPackages($membership, $data['packages']);

            return $membership->load(self::RELATIONS);
        });
    }

    public function update(Membership $membership, array $data): Membership
    {
        return DB::transaction(function () use ($membership, $data) {
            $service = $membership->service()
                ->lockForUpdate()
                ->firstOrFail();

            $service->update([
                'name' => $this->translations($data['name']),
                'description' => $this->translations($data['description']),
            ]);

            $membership->update($this->attributes($data));

            $this->syncPrice($service, $data['price']);
            $this->syncPackages($membership, $data['packages']);

            return $membership->fresh()->load(self::RELATIONS);
        });
    }

    public function delete(Membership $membership): void
    {
        DB::transaction(function () use ($membership) {
            $service = $membership->service()
                ->lockForUpdate()
                ->firstOrFail();

            $service->priceOptions()->delete();
            $membership->delete();
            $service->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $visitServiceId = $data['visit_service_id'] ?? null;

        return [
            'visit_service_id' => $visitServiceId,
            'max_visits_per_day' => $visitServiceId === null ? null : $data['max_visits_per_day'],
            'duration' => $data['duration'],
            'color' => $data['color'],
        ];
    }

    /**
     * The membership price is the service's single price option, updated in place.
     */
    private function syncPrice(Service $service, int $price): void
    {
        $option = $service->priceOptions()->first();

        if ($option === null) {
            $service->priceOptions()->create([
                'name' => null,
                'price' => $price,
                'quantity' => null,
                'unit' => PriceOptionUnit::OPTION,
            ]);

            return;
        }

        $option->update(['price' => $price]);
    }

    /**
     * Replaces the included packages.
     *
     * @param  array<int, array{service_package_id: int, trainer_id?: int|null}>  $packages
     */
    private function syncPackages(Membership $membership, array $packages): void
    {
        $priceOptions = ServicePackage::query()
            ->whereKey(array_column($packages, 'service_package_id'))
            ->pluck('price_option_id', 'id');

        $membership->packages()->delete();

        $membership->packages()->createMany(array_map(fn (array $package): array => [
            'service_package_id' => $package['service_package_id'],
            'price_option_id' => $priceOptions[$package['service_package_id']],
            'trainer_id' => $package['trainer_id'] ?? null,
        ], array_values($packages)));
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
