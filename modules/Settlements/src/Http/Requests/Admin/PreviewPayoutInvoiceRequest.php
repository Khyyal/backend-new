<?php

namespace Modules\Settlements\Http\Requests\Admin;

use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('locale', 'string', 'Invoice language.', enum: ['en', 'ar'], example: 'ar')]
#[BodyParam('payout_id', 'integer', 'Payout to preview with; omit for sample data.', required: false, nullable: true, example: 17)]
class PreviewPayoutInvoiceRequest extends UpdatePayoutInvoiceTemplateRequest
{
    /**
     * The template fields plus the preview's language and payout.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'locale' => ['required', 'string', 'in:en,ar'],
            'payout_id' => ['sometimes', 'nullable', 'integer', 'exists:center_payouts,id'],
        ];
    }
}
