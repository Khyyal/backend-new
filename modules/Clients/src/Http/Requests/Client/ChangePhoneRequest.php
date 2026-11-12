<?php

namespace Modules\Clients\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('phone_number', 'string', 'The new phone in international format: optional `+` then 6-20 digits (max 30). Must differ from the current phone and not belong to another client.', example: '+966500000004')]
class ChangePhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'phone_number' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9]{6,20}$/',
                Rule::notIn([(string) $this->user('client')?->phone_number]),
                Rule::unique('clients', 'phone_number')->withoutTrashed(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.required' => __('validation.phone_required'),
            'phone_number.string' => __('validation.phone_format'),
            'phone_number.max' => __('validation.phone_format'),
            'phone_number.regex' => __('validation.phone_format'),
            'phone_number.not_in' => __('validation.phone_same'),
            'phone_number.unique' => __('validation.phone_taken'),
        ];
    }
}
