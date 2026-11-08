<?php

namespace Modules\Clients\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Clients\Models\Client;
use Modules\Support\Enums\Gender;

class ListClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', Client::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Matches the full name (e.g. `Aisha Noor`) or the phone number; every word must match. */
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            /** Only clients of this city. */
            'city_id' => ['sometimes', 'nullable', 'integer'],
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
            /** Inclusive range on the registration date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
