<?php

namespace Modules\Services\Services;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Modules\Services\Models\Membership;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Records one `updated` activity on a Service for a whole service edit, so
 * changes of its child rows (price options, packages, schedules, trainers,
 * settings, type details and media) are audited, not only its own columns.
 */
class ServiceActivityRecorder
{
    /**
     * Run the update with model-level logging off and log the snapshot diff.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $update
     * @return TResult
     */
    public function record(Service $service, Closure $update): mixed
    {
        $before = $this->snapshot($service);

        $result = activity()->withoutLogging($update);

        $after = $this->snapshot($service);

        $changedKeys = array_keys(array_filter(
            $after,
            fn (mixed $value, string $key): bool => $value != ($before[$key] ?? null),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedKeys !== []) {
            activity()
                ->useLog('service')
                ->event('updated')
                ->performedOn($service)
                ->withChanges([
                    'old' => array_intersect_key($before, array_flip($changedKeys)),
                    'attributes' => array_intersect_key($after, array_flip($changedKeys)),
                ])
                ->log('updated');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Service $service): array
    {
        $service = Service::withTrashed()
            ->with(['serviceable', 'priceOptions.package', 'settings', 'media'])
            ->findOrFail($service->getKey());

        $serviceable = $service->serviceable;

        $snapshot = [
            'name' => $service->getTranslations('name'),
            'description' => $service->getTranslations('description'),
            'status' => $service->status?->value,
            'details' => $serviceable === null ? [] : $this->fillableValues($serviceable),
            'price_options' => $service->priceOptions
                ->sortBy(['quantity', 'price'])
                ->map(fn (PriceOption $option): array => [
                    ...$this->fillableValues($option, except: ['service_id']),
                    'package' => $option->package === null
                        ? null
                        : $this->fillableValues($option->package, except: ['service_id', 'price_option_id']),
                ])
                ->values()
                ->all(),
            'settings' => $service->settings->sortBy('key')->pluck('value', 'key')->all(),
            'media' => $service->media
                ->sortBy('id')
                ->groupBy('collection_name')
                ->map(fn ($items): array => $items->map(fn (Media $media): int => $media->id)->values()->all())
                ->all(),
        ];

        if ($serviceable !== null && method_exists($serviceable, 'schedules')) {
            $snapshot['schedules'] = $serviceable->schedules()
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->map(fn (Model $schedule): array => $this->fillableValues($schedule, except: ['schedulable_type', 'schedulable_id']))
                ->all();
        }

        if ($serviceable !== null && method_exists($serviceable, 'trainers')) {
            $snapshot['trainer_ids'] = $serviceable->trainers()->orderBy('trainers.id')->pluck('trainers.id')->all();
        }

        if ($serviceable instanceof Membership) {
            $snapshot['packages'] = $serviceable->packages()
                ->orderBy('id')
                ->get()
                ->map(fn (Model $package): array => $this->fillableValues($package, except: ['membership_id']))
                ->all();
        }

        return $snapshot;
    }

    /**
     * @param  array<int, string>  $except
     * @return array<string, mixed>
     */
    private function fillableValues(Model $model, array $except = []): array
    {
        $values = [];

        foreach (array_diff($model->getFillable(), $except) as $attribute) {
            $value = $model->getAttribute($attribute);
            $values[$attribute] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return json_decode(json_encode($values), true);
    }
}
