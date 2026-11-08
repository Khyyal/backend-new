<?php

namespace Modules\Billing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Models\Subscription;

class ChangeCenterSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('change', Subscription::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            /** `free` assigns the plan now without payment; `required` opens a pending subscription the center must pay. */
            'payment_mode' => ['required', 'in:free,required'],
            /** Payment gateway for `required` mode; defaults to the default gateway. */
            'payment_company' => ['nullable', 'string', 'max:50'],
        ];
    }
}
