<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Models\Center;

/**
 * Sets (or clears) the horse a reservation is for. Any horse of the center.
 */
#[BodyParam('horse_id', 'integer', 'A horse of this center (`GET centers/{center}/horses`); null detaches the current horse.', nullable: true, example: 5)]
class AttachReservationHorseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('editReservations', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'horse_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('horses', 'id')->where('center_id', $center->getKey())->withoutTrashed(),
            ],
        ];
    }
}
