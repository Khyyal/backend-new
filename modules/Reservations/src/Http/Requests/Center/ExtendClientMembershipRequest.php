<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Center staff extend a client membership's validity.
 */
#[BodyParam('expires_at', 'string', 'New expiry (ISO-8601 date-time), later than the current one and in the future.', example: '2026-12-01T00:00:00+00:00')]
class ExtendClientMembershipRequest extends FormRequest
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
            'expires_at' => ['required', 'date', 'after:now'],
        ];
    }
}
