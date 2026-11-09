<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Enums\DevicePlatform;

#[BodyParam('name', 'string', 'Center name (max 255).', example: 'Al Fursan Stables')]
#[BodyParam('description', 'string', 'Center description.', required: false, nullable: true, example: 'Riding lessons and trail rides for all levels.')]
#[BodyParam('contact_phone', 'string', 'Public contact phone (max 32).', example: '+966500000001')]
#[BodyParam('email', 'string', 'Unique contact email (max 255, lower-cased).', required: false, nullable: true, example: 'info@alfursan.sa')]
#[BodyParam('user', 'object', 'The owner account.', example: ['name' => 'Khalid Al-Harbi', 'phone' => '+966500000002', 'code' => '1234', 'password' => 'secret-password'])]
#[BodyParam('user.name', 'string', 'Owner full name (max 255).', example: 'Khalid Al-Harbi')]
#[BodyParam('user.phone', 'string', 'Owner phone (max 32), the one that received the registration OTP.', example: '+966500000002')]
#[BodyParam('user.code', 'string', 'OTP received from `POST /api/v1/centers/register/phone`.', example: '1234')]
#[BodyParam('user.password', 'string', 'Owner password, 8-255 characters.', example: 'secret-password')]
#[BodyParam('device_identifier', 'string', 'Device ID sent to the launch endpoint, to link the device (max 255). Can also be sent as the `X-Device-Identifier` header.', required: false, nullable: true, example: 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6')]
#[BodyParam('platform', 'string', 'Same platform sent to the launch endpoint.', required: false, nullable: true, enum: DevicePlatform::class, example: 'ios')]
class RegisterCenterFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('centers', 'email')],
            'user.name' => ['required', 'string', 'max:255'],
            'user.phone' => ['required', 'string', 'max:32'],
            'user.code' => ['required', 'string'],
            'user.password' => ['required', 'string', 'min:8', 'max:255'],
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', Rule::enum(DevicePlatform::class)],
        ];
    }
}
