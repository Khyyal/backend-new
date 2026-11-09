<?php

namespace Modules\Analytics\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Analytics\Enums\AnalyticsSection;
use Modules\Analytics\Services\CentersAnalytics;

/**
 * Filters shared by every analytics endpoint. Ranges end yesterday at the
 * latest, since today is not built yet.
 */
#[QueryParam('from', 'string', 'Range start `Y-m-d`, before today. Default: 30 complete days ago. At most 366 days.', required: false, example: '2026-10-10')]
#[QueryParam('to', 'string', 'Range end `Y-m-d`, before today. Default: yesterday.', required: false, example: '2026-11-08')]
#[QueryParam('compare', 'string', '`prior_period` (default, same length right before), `prior_year` or `none`.', required: false, enum: ['prior_period', 'prior_year', 'none'], example: 'prior_period')]
#[QueryParam('granularity', 'string', 'Series bucket; weeks start Sunday. Default from the range length.', required: false, enum: ['day', 'week', 'month'], example: 'day')]
#[QueryParam('center_id', 'integer', 'Slice center-level metrics to this center.', required: false, example: 8)]
#[QueryParam('city_id', 'integer', 'Slice center-level metrics to this city.', required: false, example: 1)]
#[QueryParam('sort', 'string', 'Centers leaderboard order. Default `gmv`.', required: false, enum: ['gmv', 'purchases_count', 'net_fee'], example: 'gmv')]
#[QueryParam('limit', 'integer', 'Size of top lists and leaderboards, 1-50. Default 10.', required: false, example: 10)]
#[QueryParam('section', 'string', 'Export only: which section to export. Default `overview`.', required: false, enum: ['overview', 'revenue', 'bookings', 'payments', 'clients', 'centers', 'promotions', 'subscriptions', 'engagement'], example: 'overview')]
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
