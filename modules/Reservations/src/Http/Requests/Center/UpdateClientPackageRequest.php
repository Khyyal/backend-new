<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Lets center staff mark a client package as controlled.
 */
#[BodyParam('controlled', 'boolean', 'Whether the center controls this package.', example: true)]
class UpdateClientPackageRequest extends FormRequest
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
            'controlled' => ['required', 'boolean'],
        ];
    }
}
