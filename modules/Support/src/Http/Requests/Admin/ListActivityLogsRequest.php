<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\Activitylog\Models\Activity;

#[QueryParam('log_name', 'string', 'Only this audited area.', required: false, enum: ListActivityLogsRequest::LOG_NAMES, example: 'service')]
#[QueryParam('subject_id', 'integer', 'Only changes to this record ID.', required: false, example: 31)]
#[QueryParam('event', 'string', 'Only this event.', required: false, enum: ['created', 'updated', 'deleted', 'restored'], example: 'updated')]
#[QueryParam('causer_id', 'integer', 'Only changes made by this actor ID.', required: false, example: 2)]
#[QueryParam('from', 'string', 'From this day inclusive, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Up to this day inclusive, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 25.', required: false, example: 25)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
class ListActivityLogsRequest extends FormRequest
{
    /** @var array<int, string> */
    public const LOG_NAMES = ['service', 'reservation', 'client_package', 'client_membership', 'system_user', 'system_role', 'subscription', 'legal_document'];

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', Activity::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** The audited record type. */
            'log_name' => ['sometimes', Rule::in(self::LOG_NAMES)],
            /** Id of the audited record; combine with `log_name` for one record's history. */
            'subject_id' => ['sometimes', 'integer'],
            'event' => ['sometimes', Rule::in(['created', 'updated', 'deleted', 'restored'])],
            /** Id of the actor (system user, center user or client). */
            'causer_id' => ['sometimes', 'integer'],
            /** Inclusive range on the log date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
