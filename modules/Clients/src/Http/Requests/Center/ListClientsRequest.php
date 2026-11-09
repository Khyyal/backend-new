<?php

namespace Modules\Clients\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Centers\Models\Center;

#[QueryParam('q', 'string', 'Matches the full name or phone; every word must match (max 100).', required: false, example: 'Noura')]
#[QueryParam('from', 'string', 'Last purchase on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Last purchase on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
#[QueryParam('page', 'integer', 'Page number (1-based); 25 per page.', required: false, example: 1)]
class ListClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageReservations', $this->center());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Matches the full name or the phone number; every word must match. */
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            /** Inclusive range on the last confirmed or completed purchase date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function center(): Center
    {
        return $this->route('center');
    }
}
