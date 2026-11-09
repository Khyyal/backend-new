<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('plan_id', 'integer', 'Active plan ID.', example: 1)]
#[BodyParam('payment_company', 'string', 'Gateway for paid plans: `moyasar`, `tamara` or `fake` (dev/test only). Default: the configured gateway.', required: false, nullable: true, enum: ['moyasar', 'tamara', 'fake'], example: 'moyasar')]
class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'payment_company' => ['nullable', 'string', Rule::in($this->availablePaymentCompanies())],
        ];
    }

    /**
     * The enabled payment providers plus the default gateway.
     *
     * @return list<string>
     */
    private function availablePaymentCompanies(): array
    {
        return collect((array) config('purchase.providers', []))
            ->filter(fn (array $provider): bool => (bool) ($provider['enabled'] ?? false))
            ->keys()
            ->push((string) config('purchase.default_gateway', 'fake'))
            ->unique()
            ->values()
            ->all();
    }
}
