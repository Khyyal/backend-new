<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Invites another client on one of the membership's free daily visits.
 */
#[BodyParam('guest_client_id', 'integer', 'Existing client to invite; not the member themselves.', example: 78)]
#[BodyParam('date', 'string', 'Day before the membership expires, `Y-m-d`.', example: '2026-06-06')]
#[BodyParam('start_time', 'string', 'Start `H:i`; required for timed visits.', required: false, nullable: true, example: 'No-example')]
class InviteToMembershipRequest extends FormRequest
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
            'guest_client_id' => ['required', 'integer', Rule::notIn([$this->user('client')?->getKey()]), Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
        ];
    }
}
