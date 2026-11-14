<?php

namespace Modules\Centers\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Client center reads accept `include`, a comma-separated list (or array) of extras.
 */
trait ParsesCenterIncludes
{
    /**
     * Accepted `include` values.
     *
     * @var list<string>
     */
    public const array INCLUDES = ['ratings'];

    public function includes(string $relation): bool
    {
        return in_array($relation, $this->input('include') ?? [], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function includeRules(): array
    {
        return [
            'include' => ['nullable', 'array'],
            'include.*' => ['string', Rule::in(self::INCLUDES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('include'))) {
            $this->merge([
                'include' => array_values(array_filter(array_map('trim', explode(',', $this->input('include'))))),
            ]);
        }
    }
}
