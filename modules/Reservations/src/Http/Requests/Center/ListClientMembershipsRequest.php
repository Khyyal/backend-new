<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\QueryParam;

/**
 * Filters for the center's client memberships list.
 */
#[QueryParam('active', 'boolean', 'Only active memberships.', required: false, example: true)]
#[QueryParam('not_active', 'boolean', 'Only the other statuses (expired, cancelled). With both flags, all are returned.', required: false, example: false)]
#[QueryParam('q', 'string', 'Client name or phone (max 100).', required: false, example: 'Noura')]
#[QueryParam('from', 'string', 'Bought on or after, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Bought on or before, `Y-m-d`.', required: false, example: '2026-05-31')]
#[QueryParam('service_id', 'integer', 'Only this service.', required: false, example: 31)]
#[QueryParam('page', 'integer', 'Page number (1-based); 25 per page.', required: false, example: 1)]
class ListClientMembershipsRequest extends FormRequest
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
            'active' => ['sometimes', 'boolean'],
            'not_active' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'service_id' => ['sometimes', 'integer'],
        ];
    }
}
