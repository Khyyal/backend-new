<?php

namespace Modules\Services\Concerns;

/**
 * Key/value settings of a serviceable model, stored on its Service.
 *
 * The model declares its allowed keys in a SETTINGS_KEYS constant. Values are
 * booleans; a key that was never saved reads as false.
 */
trait HasSettings
{
    /**
     * @return array<int, string>
     */
    public static function settingKeys(): array
    {
        return static::SETTINGS_KEYS;
    }

    /**
     * Every key of this service type with its boolean value.
     *
     * @return array<string, bool>
     */
    public function settingValues(): array
    {
        $stored = $this->service->settings->pluck('value', 'key');

        return collect(static::settingKeys())
            ->mapWithKeys(fn (string $key): array => [
                $key => filter_var($stored->get($key, false), FILTER_VALIDATE_BOOLEAN),
            ])
            ->all();
    }

    /**
     * Replace the settings: keys missing from $values are reset to false.
     *
     * @param  array<string, mixed>  $values
     */
    public function syncSettings(array $values): void
    {
        $service = $this->service()->firstOrFail();

        $rows = collect(static::settingKeys())
            ->map(fn (string $key): array => [
                'service_id' => $service->getKey(),
                'key' => $key,
                'value' => filter_var($values[$key] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            ])
            ->all();

        if ($rows !== []) {
            $service->settings()->upsert($rows, ['service_id', 'key'], ['value']);
        }

        $this->unsetRelation('service');
    }

    /**
     * Validation rules for the optional `settings` object.
     *
     * @return array<string, array<int, string>>
     */
    public static function settingsRules(): array
    {
        return [
            'settings' => ['sometimes', 'array:'.implode(',', static::settingKeys())],
            'settings.*' => ['boolean'],
        ];
    }
}
