<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Moves a reservation to another slot of the same service.
 */
#[BodyParam('date', 'string', 'New day, `Y-m-d`.', example: '2026-06-06')]
#[BodyParam('start_time', 'string', 'New start `H:i` (timed services).', required: false, nullable: true, example: '17:00')]
#[BodyParam('end_date', 'string', 'New last day of a resort stay.', required: false, nullable: true, example: 'No-example')]
#[BodyParam('trainer_id', 'integer', 'New trainer (training).', required: false, nullable: true, example: 'No-example')]
#[BodyParam('force', 'boolean', 'Move it even when the new slot has too few places left: the capacity check is skipped (every other rule still applies). Default false.', required: false, example: false)]
class RescheduleReservationRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('editReservations', $this->route('center'));
    }

    public function rules(): array
    {
        return [
            ...$this->slotRules(),
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
