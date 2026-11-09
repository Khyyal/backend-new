<?php

namespace Modules\Billing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Billing\Models\Subscription;

#[BodyParam('plan_id', 'integer', 'Plan to switch to (must be active and not the current plan).', example: 1)]
#[BodyParam('payment_mode', 'string', '`free`: cancel the current subscription and start the plan now with no payment. `required`: open a pending subscription with an online payment; the current one stays until paid (paid plans without a trial only).', enum: ['free', 'required'], example: 'free')]
#[BodyParam('payment_company', 'string', 'Gateway for `required` mode (max 50).', required: false, nullable: true, example: 'moyasar')]
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
