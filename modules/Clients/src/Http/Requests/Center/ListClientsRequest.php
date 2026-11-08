<?php

namespace Modules\Clients\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;

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
