<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Spends one package credit on a slot.
 */
#[BodyParam('date', 'string', 'Day, `Y-m-d`.', example: '2026-06-05')]
#[BodyParam('start_time', 'string', 'Start `H:i`.', example: '16:00')]
#[BodyParam('end_date', 'string', 'Not used by package services; leave empty.', required: false, nullable: true, example: 'No-example')]
#[BodyParam('trainer_id', 'integer', 'Trainer, for training packages (fixed when the package came with a membership).', required: false, nullable: true, example: 6)]
class BookFromPackageRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->slotRules(),
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
