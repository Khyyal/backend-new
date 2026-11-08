<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class ListActivityLogsRequest extends FormRequest
{
    /** @var array<int, string> */
    public const LOG_NAMES = ['service', 'reservation', 'client_package', 'client_membership', 'system_user', 'system_role'];

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
