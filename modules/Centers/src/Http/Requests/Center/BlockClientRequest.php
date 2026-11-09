<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('client_id', 'integer', 'Existing client ID.', example: 77)]
#[BodyParam('reason', 'string', 'Why the client is blocked (max 1000). Blocking again updates the reason.', example: 'Repeated no-shows.')]
class BlockClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
