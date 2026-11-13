<?php

namespace Modules\Services\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Models\Stabling;
use Modules\Support\Enums\ActivationStatus;

/**
 * Domain graph of a stabling service: the Stabling record, its Service and
 * its price options (monthly prices, an optional daily price and optional
 * extras). Price options are updated in place by id so their ids stay stable;
 * options missing from the payload are soft-deleted.
 */
class StablingService
{
    /**
     * @param  array{center_id: int, name: array<string, ?string>, description: array<string, ?string>, monthly_prices: array<int, array{id?: int, months: int, price: int}>, daily_price?: array{id?: int, price: int}|null, extra_options?: array<int, array{id?: int, name: string, price: int}>}  $data
     */
    public function create(array $data): Stabling
    {
        return DB::transaction(function () use ($data) {
            $stabling = Stabling::query()->create();

            $service = $stabling->service()->create([
                'name' => $this->translations($data['name']),
                'description' => $this->translations($data['description']),
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::Stabling,
            ]);

            $this->syncPriceOptions($service, $data);

            return $stabling->load('service.priceOptions');
        });
    }

    /**
     * @param  array{name: array<string, ?string>, description: array<string, ?string>, monthly_prices: array<int, array{id?: int, months: int, price: int}>, daily_price?: array{id?: int, price: int}|null, extra_options?: array<int, array{id?: int, name: string, price: int}>}  $data
     *
     * @throws ValidationException
     */
    public function update(Stabling $stabling, array $data): Stabling
    {
        return DB::transaction(function () use ($stabling, $data) {
            $service = $stabling->service()->lockForUpdate()->firstOrFail();

            $service->update([
                'name' => $this->translations($data['name']),
                'description' => $this->translations($data['description']),
            ]);

            $this->syncPriceOptions($service, $data);

            return $stabling->fresh()->load('service.priceOptions');
        });
    }

    public function delete(Stabling $stabling): void
    {
        DB::transaction(function () use ($stabling) {
            $service = $stabling->service()->lockForUpdate()->firstOrFail();

            $service->priceOptions()->delete();
            $stabling->delete();
            $service->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function syncPriceOptions(Service $service, array $data): void
    {
        $existing = $service->priceOptions()->get()->keyBy('id');
        $keptIds = [];

        foreach (array_values($data['monthly_prices']) as $index => $option) {
            $keptIds[] = $this->upsert($service, $existing, $option, "monthly_prices.{$index}.id", [
                'name' => null,
                'price' => $option['price'],
                'quantity' => $option['months'],
                'unit' => PriceOptionUnit::MONTH,
            ]);
        }

        if (($data['daily_price'] ?? null) !== null) {
            $keptIds[] = $this->upsert($service, $existing, $data['daily_price'], 'daily_price.id', [
                'name' => null,
                'price' => $data['daily_price']['price'],
                'quantity' => 1,
                'unit' => PriceOptionUnit::DAY,
            ]);
        }

        foreach (array_values($data['extra_options'] ?? []) as $index => $option) {
            $keptIds[] = $this->upsert($service, $existing, $option, "extra_options.{$index}.id", [
                'name' => $option['name'],
                'price' => $option['price'],
                'quantity' => null,
                'unit' => PriceOptionUnit::OPTION,
            ]);
        }

        $removedIds = $existing->keys()->diff($keptIds)->all();

        if ($removedIds !== []) {
            PriceOption::query()->whereIn('id', $removedIds)->delete();
        }
    }

    /**
     * Updates the option named by the payload `id` (it must belong to the
     * service and not be used twice) or creates a new one.
     *
     * @param  Collection<int, PriceOption>  $existing  options not yet matched, keyed by id
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function upsert(Service $service, Collection $existing, array $option, string $errorKey, array $attributes): int
    {
        if (! isset($option['id'])) {
            return $service->priceOptions()->create($attributes)->getKey();
        }

        $model = $existing->pull($option['id']) ?? throw ValidationException::withMessages([
            $errorKey => ['The selected price option does not belong to this service.'],
        ]);

        $model->update($attributes);

        return $model->getKey();
    }

    /**
     * @param  array<string, ?string>  $values
     * @return array<string, string>
     */
    private function translations(array $values): array
    {
        return array_filter(
            ['ar' => $values['ar'], 'en' => $values['en'] ?? null],
            fn (?string $value) => $value !== null,
        );
    }
}
