<?php

namespace Modules\Billing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\AdminSubscriptionQuery;

#[QueryParam('center_id', 'integer', 'Only this center.', required: false, example: 8)]
#[QueryParam('plan_id', 'integer', 'Only this plan.', required: false, example: 1)]
#[QueryParam('status', 'string', 'Only this status.', required: false, enum: SubscriptionStatus::class, example: 'active')]
#[QueryParam('from', 'string', 'Starting on or after, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Starting on or before, `Y-m-d`.', required: false, example: '2026-05-31')]
#[QueryParam('sort', 'string', 'Sort column; prefix `-` for descending. Default newest first.', required: false, enum: AdminSubscriptionQuery::SORTS, example: 'ends_at')]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 25.', required: false, example: 25)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
class ListSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', Subscription::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Only subscriptions of this center. */
            'center_id' => ['sometimes', 'integer'],
            'plan_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            /** Inclusive range on the start date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['sometimes', 'nullable', Rule::in(AdminSubscriptionQuery::SORTS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
