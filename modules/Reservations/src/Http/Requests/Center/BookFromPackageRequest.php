<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Center staff spend one credit of a client's package on a slot.
 */
#[BodyParam('date', 'string', 'Day, `Y-m-d`.', example: '2026-06-05')]
#[BodyParam('start_time', 'string', 'Start `H:i`.', example: '16:00')]
#[BodyParam('end_date', 'string', 'Not used by package services; leave empty.', required: false, nullable: true, example: 'No-example')]
#[BodyParam('trainer_id', 'integer', 'Trainer, for training packages (fixed when the package is bound to a trainer).', required: false, nullable: true, example: 6)]
#[BodyParam('notes', 'string', 'Staff notes (max 1000).', required: false, nullable: true, example: 'Prefers a calm horse.')]
class BookFromPackageRequest extends FormRequest
{
    use HasBookingRules;

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
            ...$this->slotRules(),
            'start_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
