<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('immediately', 'boolean', '`true` ends it now; `false` (default) keeps it until `ends_at`.', required: false, nullable: true, example: false)]
class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'immediately' => ['nullable', 'boolean'],
        ];
    }
}
