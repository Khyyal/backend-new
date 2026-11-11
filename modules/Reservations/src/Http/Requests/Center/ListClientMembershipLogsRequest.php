<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Reservations\Enums\ClientMembershipLogType;

/**
 * Filters for a membership's log.
 */
#[QueryParam('type', 'string', 'Only this kind of entry.', required: false, enum: ['invite', 'check_in'], example: 'check_in')]
#[QueryParam('page', 'integer', 'Page number (1-based); 25 per page.', required: false, example: 1)]
class ListClientMembershipLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageReservations', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(ClientMembershipLogType::class)],
        ];
    }
}
