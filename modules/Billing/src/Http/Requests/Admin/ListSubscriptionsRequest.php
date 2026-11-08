<?php

namespace Modules\Billing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Subscription;

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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
