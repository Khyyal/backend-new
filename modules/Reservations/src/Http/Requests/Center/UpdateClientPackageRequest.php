<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Center staff change a paid client package: mark it controlled, bind or free
 * its trainer, or extend its validity. Send at least one field.
 */
#[BodyParam('controlled', 'boolean', 'Whether the center controls this package.', required: false, example: true)]
#[BodyParam('trainer_id', 'integer', 'Trainer of the center who teaches the package\'s training service; null frees the package from its trainer.', required: false, nullable: true, example: 6)]
#[BodyParam('expires_at', 'string', 'New expiry (ISO-8601 date-time), later than the current one and in the future. Brings an expired package with sessions left back to active.', required: false, example: '2026-12-01T00:00:00+00:00')]
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
            'controlled' => ['sometimes', 'boolean'],
            'trainer_id' => ['sometimes', 'nullable', 'integer'],
            'expires_at' => ['sometimes', 'date', 'after:now'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['controlled', 'trainer_id', 'expires_at'])) {
                    $validator->errors()->add('controlled', __('validation.required_without_all', ['values' => 'trainer_id / expires_at']));
                }
            },
        ];
    }
}
