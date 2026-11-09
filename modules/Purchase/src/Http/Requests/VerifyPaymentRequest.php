<?php

namespace Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('provider_payment_id', 'string', 'Moyasar payment ID returned by the SDK (required the first time for `moyasar`, ignored for `tamara`; max 100).', required: false, nullable: true, example: 'f3e5a1b2-7c9d-4e0f-8a1b-2c3d4e5f6a7b')]
class VerifyPaymentRequest extends FormRequest
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
            'provider_payment_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
