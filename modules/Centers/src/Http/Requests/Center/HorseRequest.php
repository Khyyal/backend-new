<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Body shared by create and update. Update replaces name, room and owner;
 * `image` is left untouched when omitted.
 */
#[BodyParam('name', 'string', 'Horse name (max 255).', example: 'Najm')]
#[BodyParam('room', 'integer', 'Room (stall) number, min 1. Rooms may be shared.', example: 12)]
#[BodyParam('client_id', 'integer', 'Owning client. Null or omitted: the center owns the horse. The client is added to the center\'s clients if needed.', required: false, nullable: true, example: 77)]
#[BodyParam('image', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or the current image; null removes it; omit to keep it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
class HorseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageHorses', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'room' => ['required', 'integer', 'min:1'],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->withoutTrashed()],
            'image' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
