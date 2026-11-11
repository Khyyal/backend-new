<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Sets the name the client gives one of their packages.
 */
#[BodyParam('knight_name', 'string', 'Name for the package (max 255); null clears it.', nullable: true, example: 'Thunder')]
class UpdatePackageRequest extends FormRequest
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
            'knight_name' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}
