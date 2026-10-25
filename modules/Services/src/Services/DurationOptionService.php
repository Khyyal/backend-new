<?php

namespace Modules\Services\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServicePackage;

/**
 * Keeps the duration price options (minutes) of a service and the packages
 * sold on them in sync with a create/update payload. Must run inside the
 * caller's transaction.
 *
 * Options carrying an `id` are updated in place so their package survives;
 * options without one are created; existing options missing from the payload
 * are deleted together with their package.
 */
class DurationOptionService
{
    /**
     * @param  array<int, array{id?: int, duration: int, price: float|int|string}>  $options
     * @param  array<int, array{price_option: int, count: int, price: float|int|string, duration: int}>|null  $packages
     *                                                                                                                   `null` leaves the existing packages untouched; an array replaces them.
     *
     * @throws ValidationException
     */
    public function sync(Service $service, array $options, ?array $packages = null): void
    {
        $existing = $service->priceOptions()->get()->keyBy('id');

        /** @var array<int, PriceOption> $ordered */
        $ordered = [];

        foreach (array_values($options) as $index => $data) {
            $attributes = [
                'name' => null,
                'price' => $data['price'],
                'quantity' => $data['duration'],
                'unit' => PriceOptionUnit::MINUTE,
            ];

            if (isset($data['id'])) {
                $option = $existing->get($data['id']) ?? throw ValidationException::withMessages([
                    "price_options.{$index}.id" => ['The selected price option does not belong to this service.'],
                ]);
                $option->update($attributes);
            } else {
                $option = $service->priceOptions()->create($attributes);
            }

            $ordered[$index] = $option;
        }

        $keptIds = collect($ordered)->map->getKey()->all();
        $removedIds = $existing->keys()->diff($keptIds)->all();

        if ($removedIds !== []) {
            ServicePackage::query()->whereIn('price_option_id', $removedIds)->delete();
            PriceOption::query()->whereIn('id', $removedIds)->delete();
        }

        if ($packages !== null) {
            $this->syncPackages($service, $ordered, $packages);
        }
    }

    /**
     * @param  array<int, PriceOption>  $ordered
     * @param  array<int, array<string, mixed>>  $packages
     */
    private function syncPackages(Service $service, array $ordered, array $packages): void
    {
        $referenced = [];

        foreach (array_values($packages) as $index => $package) {
            $option = $ordered[$package['price_option']] ?? throw ValidationException::withMessages([
                "packages.{$index}.price_option" => ['The price option index does not exist in price_options.'],
            ]);

            if (in_array($option->getKey(), $referenced, true)) {
                throw ValidationException::withMessages([
                    "packages.{$index}.price_option" => ['A price option can only have one package.'],
                ]);
            }

            // A previously removed (soft-deleted) package of this option is revived,
            // since price_option_id stays unique across trashed rows.
            $model = ServicePackage::withTrashed()->firstOrNew(['price_option_id' => $option->getKey()]);
            $model->fill([...Arr::only($package, ['count', 'price', 'duration']), 'service_id' => $service->getKey()]);
            $model->deleted_at = null;
            $model->save();

            $referenced[] = $option->getKey();
        }

        ServicePackage::query()
            ->whereIn('price_option_id', collect($ordered)->map->getKey()->all())
            ->whereNotIn('price_option_id', $referenced)
            ->delete();
    }
}
