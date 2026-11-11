<?php

namespace Modules\Settlements\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Models\PayoutInvoiceTemplate;
use Modules\Support\Services\LegalHtmlSanitizer;

#[BodyParam('company_name', 'object', 'Company name shown on the invoice; both locales are required.', example: ['en' => 'Khyyal', 'ar' => 'خيال'])]
#[BodyParam('company_name.en', 'string', 'English name (max 150).', example: 'Khyyal')]
#[BodyParam('company_name.ar', 'string', 'Arabic name (max 150).', example: 'خيال')]
#[BodyParam('company_address', 'object', 'Address per locale (max 300 each).', required: false, example: ['en' => 'Riyadh, Saudi Arabia', 'ar' => 'الرياض، السعودية'])]
#[BodyParam('tax_number', 'string', 'VAT number (max 50).', required: false, nullable: true, example: '300000000000003')]
#[BodyParam('commercial_registration', 'string', 'Commercial registration number (max 50).', required: false, nullable: true, example: '1010000000')]
#[BodyParam('email', 'string', 'Contact email.', required: false, nullable: true, example: 'finance@khyyal.com')]
#[BodyParam('phone', 'string', 'Contact phone (max 30).', required: false, nullable: true, example: '+966500000000')]
#[BodyParam('accent_color', 'string', 'Hex color of titles and table headers, `#RRGGBB`.', example: '#1F4E79')]
#[BodyParam('header_note', 'object', 'HTML shown under the header, per locale (max 20,000 each). Sanitized like legal documents.', required: false, example: ['en' => '<p>Weekly settlement.</p>', 'ar' => '<p>تسوية أسبوعية.</p>'])]
#[BodyParam('footer_note', 'object', 'HTML shown after the total, per locale (max 20,000 each). Sanitized like legal documents.', required: false, example: ['en' => '<p>Thank you.</p>', 'ar' => '<p>شكراً لكم.</p>'])]
#[BodyParam('show_bank_details', 'boolean', 'Show the bank details box.', example: true)]
#[BodyParam('show_entry_summary', 'boolean', 'Show totals per entry type.', example: true)]
#[BodyParam('show_entries_table', 'boolean', 'List every ledger entry.', example: true)]
#[BodyParam('show_signature', 'boolean', 'Show signature and stamp lines.', example: true)]
#[BodyParam('logo', 'string', 'UUID of a JPEG or PNG temporary upload from `POST /api/v1/media`; null removes the logo; omit to keep it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
class UpdatePayoutInvoiceTemplateRequest extends FormRequest
{
    public const NOTE_MAX_LENGTH = 20_000;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageInvoiceTemplate', CenterPayout::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'array:en,ar'],
            'company_name.en' => ['required', 'string', 'max:150'],
            'company_name.ar' => ['required', 'string', 'max:150'],
            'company_address' => ['sometimes', 'nullable', 'array:en,ar'],
            'company_address.*' => ['nullable', 'string', 'max:300'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'commercial_registration' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'header_note' => ['sometimes', 'nullable', 'array:en,ar'],
            'header_note.*' => ['nullable', 'string', 'max:'.self::NOTE_MAX_LENGTH],
            'footer_note' => ['sometimes', 'nullable', 'array:en,ar'],
            'footer_note.*' => ['nullable', 'string', 'max:'.self::NOTE_MAX_LENGTH],
            'show_bank_details' => ['required', 'boolean'],
            'show_entry_summary' => ['required', 'boolean'],
            'show_entries_table' => ['required', 'boolean'],
            'show_signature' => ['required', 'boolean'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * The validated template fields, with notes sanitized and blank
     * translations dropped.
     *
     * @return array<string, mixed>
     */
    public function templateAttributes(LegalHtmlSanitizer $sanitizer): array
    {
        $validated = $this->validated();
        $attributes = [];

        foreach (PayoutInvoiceTemplate::TRANSLATABLE as $field) {
            $values = [];
            foreach (PayoutInvoiceTemplate::LOCALES as $locale) {
                $value = trim((string) ($validated[$field][$locale] ?? ''));
                if (in_array($field, ['header_note', 'footer_note'], true)) {
                    $value = trim($sanitizer->sanitize($value));
                    $value = in_array($value, ['', '<p></p>'], true) ? '' : $value;
                }
                if ($value !== '') {
                    $values[$locale] = $value;
                }
            }
            $attributes[$field] = $values;
        }

        foreach (['tax_number', 'commercial_registration', 'email', 'phone'] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $attributes[$field] = $value === '' ? null : $value;
        }

        $attributes['accent_color'] = strtoupper($validated['accent_color']);

        foreach (PayoutInvoiceTemplate::SECTIONS as $field) {
            $attributes[$field] = (bool) $validated[$field];
        }

        return $attributes;
    }
}
