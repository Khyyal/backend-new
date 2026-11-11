<?php

namespace Modules\Search\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GlobalSearchRequest extends FormRequest
{
    public const DEFAULT_LIMIT = 5;

    public const MAX_LIMIT = 10;

    /**
     * Each group checks its own permission; any authenticated system user may call the endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'locale' => ['nullable', 'string', 'in:ar,en'],
        ];
    }

    /**
     * Language of translated titles: `locale`, else Accept-Language, else Arabic.
     */
    public function searchLocale(): string
    {
        return $this->input('locale') ?? $this->getPreferredLanguage(['ar', 'en']) ?? 'ar';
    }

    public function term(): string
    {
        return $this->string('q')->trim()->toString();
    }

    public function limit(): int
    {
        return $this->integer('limit', self::DEFAULT_LIMIT);
    }
}
