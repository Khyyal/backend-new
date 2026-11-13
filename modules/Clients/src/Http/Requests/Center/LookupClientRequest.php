<?php

namespace Modules\Clients\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\QueryParam;

#[QueryParam('phone_number', 'string', 'Exact phone, as the client registered it: optional `+` then 6-20 digits (max 30).', example: '+966500000003')]
class LookupClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageReservations', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9]{6,20}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => __('validation.phone_format'),
        ];
    }
}
