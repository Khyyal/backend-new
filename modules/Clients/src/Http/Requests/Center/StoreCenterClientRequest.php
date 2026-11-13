<?php

namespace Modules\Clients\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('phone_number', 'string', 'Phone in international format: optional `+` then 6-20 digits (max 30). Must not belong to another client.', example: '+966500000003')]
#[BodyParam('first_name', 'string', 'First name (max 255).', example: 'Noura')]
#[BodyParam('last_name', 'string', 'Last name (max 255).', example: 'Al-Qahtani')]
class StoreCenterClientRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
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
