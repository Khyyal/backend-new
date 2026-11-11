<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Extra sessions given to a client package.
 */
#[BodyParam('sessions', 'integer', 'Sessions to add (1-100); they raise both the total and the remaining sessions.', example: 4)]
class AddPackageSessionsRequest extends FormRequest
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
            'sessions' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
