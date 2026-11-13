<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Reservations\Services\HorseStayBoardQuery;

#[QueryParam('type', 'string', '`all` (default): active stays and horses without one; `active`: active stays only; `others`: horses without an active stay.', required: false, enum: HorseStayBoardQuery::TYPES, example: 'all')]
#[QueryParam('q', 'string', 'Matches the horse name or the client\'s name or phone; every word must match (max 100).', required: false, example: 'Najm')]
#[QueryParam('page', 'integer', 'Page number (1-based); 25 per page.', required: false, example: 1)]
class ListHorseStaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewHorseStays', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', Rule::in(HorseStayBoardQuery::TYPES)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
