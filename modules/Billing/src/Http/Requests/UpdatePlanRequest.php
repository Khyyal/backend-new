<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

#[BodyParam('name', 'object', 'Name keyed by locale.', required: false, example: ['en' => 'Pro', 'ar' => 'احترافي'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, example: 'Pro')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: false, nullable: true, example: 'احترافي')]
#[BodyParam('slug', 'string', 'Unique slug (max 255); generated from `name.en` when omitted, suffixed when taken.', required: false, nullable: true, example: 'pro')]
#[BodyParam('description', 'object', 'Description keyed by locale.', required: false, nullable: true, example: ['en' => 'For growing centers.', 'ar' => 'للمراكز النامية.'])]
#[BodyParam('description.en', 'string', 'English description.', required: false, nullable: true, example: 'For growing centers.')]
#[BodyParam('description.ar', 'string', 'Arabic description.', required: false, nullable: true, example: 'للمراكز النامية.')]
#[BodyParam('price', 'integer', 'Price per interval in halalas (0 = free).', required: false, example: 49900)]
#[BodyParam('billing_interval', 'string', 'Billing period.', required: false, enum: BillingInterval::class, example: 'monthly')]
#[BodyParam('display_features', 'string[]', 'Marketing bullet points for the plan card.', required: false, nullable: true, example: ['Unlimited bookings', 'Custom website'])]
#[BodyParam('status', 'string', 'Default `active`.', required: false, nullable: true, enum: PlanStatus::class, example: 'active')]
#[BodyParam('trial_days', 'integer', 'Free trial days, 0-365. Default 0.', required: false, nullable: true, example: 14)]
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
            'price' => ['nullable', 'integer', 'gte:0'],
            'billing_interval' => ['nullable', Rule::enum(BillingInterval::class)],
            'display_features' => ['nullable', 'array'],
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
            'trial_days' => ['nullable', 'integer', 'gte:0', 'max:365'],
        ];
    }
}
