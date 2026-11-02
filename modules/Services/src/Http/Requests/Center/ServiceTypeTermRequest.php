<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Services\Enums\ServiceType;

class ServiceTypeTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('type');

        return Gate::forUser($this->user())->allows('manageServices', [
            $this->route('center'),
            $type instanceof ServiceType ? $type : ServiceType::from($type),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'terms' => ['required', 'array'],
            'terms.ar' => ['required', 'string'],
            'terms.en' => ['nullable', 'string'],
        ];
    }
}
