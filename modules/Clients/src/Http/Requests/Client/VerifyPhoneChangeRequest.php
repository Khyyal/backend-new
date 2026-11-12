<?php

namespace Modules\Clients\Http\Requests\Client;

use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('phone_number', 'string', 'The same new phone sent to `POST /api/v1/clients/phone`.', example: '+966500000004')]
#[BodyParam('code', 'string', 'The 4-digit code received by SMS on the new phone.', example: '1234')]
class VerifyPhoneChangeRequest extends ChangePhoneRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'digits:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'code.required' => __('validation.code_required'),
            'code.digits' => __('validation.code_digits'),
        ];
    }
}
