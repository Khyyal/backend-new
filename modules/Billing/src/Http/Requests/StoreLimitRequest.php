<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('key', 'string', 'Unique machine key (max 255).', required: true, example: 'max_services')]
#[BodyParam('name', 'string', 'Display name (max 255).', required: true, example: 'Maximum services')]
class StoreLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'unique:limits,key'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
