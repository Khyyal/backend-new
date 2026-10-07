<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ServiceTypeTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('manageServices', $this->route('center'));
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
