<?php

namespace Modules\Settlements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('reference', 'string', 'Bank transfer reference (max 255).', example: 'TRX-20260608-0017')]
class MarkCenterPayoutPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
        ];
    }
}
