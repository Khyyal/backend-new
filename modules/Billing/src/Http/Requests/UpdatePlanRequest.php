<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id ?? $this->route('plan');

        return [
            'name' => ['nullable', 'array', 'min:1'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.ar' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'gte:0'],
            'billing_interval' => ['nullable', Rule::enum(BillingInterval::class)],
            'display_features' => ['nullable', 'array'],
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
            'trial_days' => ['nullable', 'integer', 'gte:0', 'max:365'],
        ];
    }
}
