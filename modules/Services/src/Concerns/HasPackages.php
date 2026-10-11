<?php

namespace Modules\Services\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Package validation for a serviceable model. Packages hang off the service's price
 * options and are managed through the service's own create/update request.
 */
trait HasPackages
{
    /**
     * Validation rules for the optional `price_options.*.id` and `packages`.
     * `packages.*.price_option` is the 0-based index into `price_options`.
     *
     * @return array<string, array<int, string>>
     */
    public static function packageRules(): array
    {
        return [
            'price_options.*.id' => ['sometimes', 'integer', 'distinct'],
            'packages' => ['sometimes', 'array', 'max:20'],
            'packages.*.price_option' => ['required', 'integer', 'min:0', 'distinct'],
            'packages.*.count' => ['required', 'integer', 'min:1'],
            'packages.*.price' => ['required', 'numeric', 'min:0'],
            'packages.*.duration' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    /**
     * Each package must point at an existing entry of `price_options`.
     *
     * @param  array<string, mixed>  $input
     */
    public static function validatePackageIndexes(Validator $validator, array $input): void
    {
        $optionCount = is_array($input['price_options'] ?? null) ? count($input['price_options']) : 0;

        foreach ((array) ($input['packages'] ?? []) as $index => $package) {
            $option = is_array($package) ? ($package['price_option'] ?? null) : null;

            if (is_int($option) && $option >= $optionCount && ! $validator->errors()->has("packages.{$index}.price_option")) {
                $validator->errors()->add("packages.{$index}.price_option", 'The price option index does not exist in price_options.');
            }
        }
    }
}
