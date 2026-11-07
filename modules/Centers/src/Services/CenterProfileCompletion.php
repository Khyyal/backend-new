<?php

namespace Modules\Centers\Services;

use Modules\Centers\Models\Center;
use Modules\Support\Enums\ActivationStatus;

/**
 * Tells which profile steps a center has done and how to finish the others.
 */
class CenterProfileCompletion
{
    /**
     * @return array{
     *     is_complete: bool,
     *     completed_steps: int,
     *     total_steps: int,
     *     percentage: int,
     *     missing: list<string>,
     *     steps: list<array{key: string, done: bool, fields: list<string>, action: array{method: string, path: string}}>
     * }
     */
    public function for(Center $center): array
    {
        $center->loadMissing('media');

        $hasMedia = fn (string $collection): bool => $center->media->contains('collection_name', $collection);
        $filled = fn (mixed $value): bool => is_string($value) ? trim($value) !== '' : $value !== null;
        $profile = ['method' => 'PATCH', 'path' => "centers/{$center->id}"];

        $steps = [
            $this->step('city', $center->city_id !== null, ['city_id'], $profile),
            $this->step('description', $filled($center->description), ['description'], $profile),
            $this->step('address', $filled($center->address), ['address'], $profile),
            $this->step('location', $center->lat !== null && $center->lng !== null, ['lat', 'lng'], $profile),
            $this->step('logo', $hasMedia('logo'), ['logo'], $profile),
            $this->step('cover', $hasMedia('cover'), ['cover'], $profile),
            $this->step('images', $hasMedia('images'), ['images'], $profile),
            $this->step('tags', $center->tags()->exists(), ['tags'], $profile),
            $this->step(
                'service',
                $center->services()->where('status', ActivationStatus::ACTIVE->value)->exists(),
                [],
                ['method' => 'GET', 'path' => "centers/{$center->id}/service-types"],
            ),
        ];

        $done = count(array_filter($steps, fn (array $step): bool => $step['done']));
        $total = count($steps);

        return [
            'is_complete' => $done === $total,
            'completed_steps' => $done,
            'total_steps' => $total,
            'percentage' => intdiv($done * 100, $total),
            'missing' => array_values(array_map(
                fn (array $step): string => $step['key'],
                array_filter($steps, fn (array $step): bool => ! $step['done']),
            )),
            'steps' => $steps,
        ];
    }

    /**
     * @param  list<string>  $fields
     * @param  array{method: string, path: string}  $action
     * @return array{key: string, done: bool, fields: list<string>, action: array{method: string, path: string}}
     */
    private function step(string $key, bool $done, array $fields, array $action): array
    {
        return ['key' => $key, 'done' => $done, 'fields' => $fields, 'action' => $action];
    }
}
