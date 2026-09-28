<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'array', 'min:1'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:plans,slug'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.ar' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'gte:0'],
            'billing_interval' => ['required', Rule::enum(BillingInterval::class)],
            'display_features' => ['nullable', 'array'],
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
            'trial_days' => ['nullable', 'integer', 'gte:0', 'max:365'],
        ];
    }
}
