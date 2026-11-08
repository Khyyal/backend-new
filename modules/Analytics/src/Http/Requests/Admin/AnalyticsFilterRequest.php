<?php

namespace Modules\Analytics\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Analytics\Enums\AnalyticsSection;
use Modules\Analytics\Services\CentersAnalytics;

/**
 * Filters shared by every analytics endpoint. Ranges end yesterday at the
 * latest, since today is not built yet.
 */
class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:today'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:today'],
            'compare' => ['sometimes', Rule::in(['prior_period', 'prior_year', 'none'])],
            'granularity' => ['sometimes', Rule::in(['day', 'week', 'month'])],
            'center_id' => ['sometimes', 'nullable', 'integer', Rule::exists('centers', 'id')],
            'city_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cities', 'id')],
            'sort' => ['sometimes', Rule::in(CentersAnalytics::SORTS)],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
            'section' => ['sometimes', Rule::enum(AnalyticsSection::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                $to = $this->filled('to') ? CarbonImmutable::parse($this->input('to')) : CarbonImmutable::yesterday();
                $from = $this->filled('from') ? CarbonImmutable::parse($this->input('from')) : $to->subDays(29);
                $maxDays = (int) config('analytics.max_range_days');

                if ($from->greaterThan($to)) {
                    $validator->errors()->add('from', __('validation.before_or_equal', ['attribute' => 'from', 'date' => $to->toDateString()]));
                } elseif ($from->diffInDays($to) + 1 > $maxDays) {
                    $validator->errors()->add('from', __('analytics::analytics.range_too_long', ['days' => $maxDays]));
                }
            },
        ];
    }
}
