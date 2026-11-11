<?php

namespace Modules\Settlements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Settlements\Models\PayoutInvoiceTemplate;
use Modules\Support\Http\Resources\MediaResource;

/**
 * The layout of printed payout invoices. Translatable fields are objects
 * keyed by `en` and `ar`; missing locales are null.
 *
 * @property PayoutInvoiceTemplate $resource
 */
class PayoutInvoiceTemplateResource extends JsonResource
{
    #[ResponseField('company_name', 'object', 'Company name per locale.', example: ['en' => 'Khyyal', 'ar' => 'خيال'])]
    #[ResponseField('company_address', 'object', 'Address per locale.', example: ['en' => 'Riyadh, Saudi Arabia', 'ar' => 'الرياض، السعودية'])]
    #[ResponseField('tax_number', 'string', 'VAT number.', nullable: true, example: '300000000000003')]
    #[ResponseField('commercial_registration', 'string', 'Commercial registration number.', nullable: true, example: '1010000000')]
    #[ResponseField('email', 'string', 'Contact email.', nullable: true, example: 'finance@khyyal.com')]
    #[ResponseField('phone', 'string', 'Contact phone.', nullable: true, example: '+966500000000')]
    #[ResponseField('accent_color', 'string', 'Hex color `#RRGGBB`.', example: '#1F4E79')]
    #[ResponseField('header_note', 'object', 'Sanitized HTML under the header, per locale.', example: ['en' => '<p>Weekly settlement.</p>', 'ar' => null])]
    #[ResponseField('footer_note', 'object', 'Sanitized HTML after the total, per locale.', example: ['en' => null, 'ar' => null])]
    #[ResponseField('show_bank_details', 'boolean', 'Bank details box shown.', example: true)]
    #[ResponseField('show_entry_summary', 'boolean', 'Totals per entry type shown.', example: true)]
    #[ResponseField('show_entries_table', 'boolean', 'Every ledger entry listed.', example: true)]
    #[ResponseField('show_signature', 'boolean', 'Signature and stamp lines shown.', example: true)]
    #[ResponseField('logo', 'object', 'Logo (`MediaResource`); null when none.', nullable: true)]
    #[ResponseField('updated_at', 'string', 'Last change (ISO-8601).', example: '2026-11-11T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        $template = $this->resource;
        $translations = [];

        foreach (PayoutInvoiceTemplate::TRANSLATABLE as $field) {
            foreach (PayoutInvoiceTemplate::LOCALES as $locale) {
                $translations[$field][$locale] = $template->getTranslation($field, $locale, false) ?: null;
            }
        }

        return [
            ...$translations,
            'tax_number' => $template->tax_number,
            'commercial_registration' => $template->commercial_registration,
            'email' => $template->email,
            'phone' => $template->phone,
            'accent_color' => $template->accent_color,
            'show_bank_details' => $template->show_bank_details,
            'show_entry_summary' => $template->show_entry_summary,
            'show_entries_table' => $template->show_entries_table,
            'show_signature' => $template->show_signature,
            'logo' => ($logo = $template->getFirstMedia('logo')) !== null ? new MediaResource($logo) : null,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
