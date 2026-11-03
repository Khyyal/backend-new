<?php

namespace Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Support\Enums\DevicePlatform;

class LaunchDeviceRequest extends FormRequest
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
            'device_identifier' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'fcm_token' => ['nullable', 'string', 'max:1024'],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }
}
