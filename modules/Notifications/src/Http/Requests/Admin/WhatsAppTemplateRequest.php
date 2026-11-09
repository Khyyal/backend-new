<?php

namespace Modules\Notifications\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Notifications\Enums\RecipientField;
use Modules\Notifications\Enums\TemplateVariableSource;
use Modules\Notifications\Models\WhatsAppTemplate;

/**
 * Create (POST) or update (PUT/PATCH) a WhatsApp template.
 */
#[BodyParam('name', 'string', 'Internal name (max 255).', example: 'Booking reminder')]
#[BodyParam('provider_name', 'string', 'Approved template name in Voxa (lower-case letters, digits, `_`; max 255); unique per language.', example: 'booking_reminder')]
#[BodyParam('language', 'string', 'Approved template language.', enum: ['ar', 'en'], example: 'ar')]
#[BodyParam('body', 'string', 'Template text for preview (max 4096).', required: false, nullable: true, example: 'مرحبا {{1}}، نذكرك بموعدك في {{2}}.')]
#[BodyParam('variables', 'object[]', 'Placeholders `{{1}}`, `{{2}}`… in order (max 20); send [] when none.', example: [['label' => 'Client name', 'default_source' => 'recipient', 'default_field' => 'first_name']])]
#[BodyParam('variables[].label', 'string', 'Label shown when filling campaigns (max 100).', example: 'Client name')]
#[BodyParam('variables[].default_source', 'string', 'Default binding source.', required: false, nullable: true, enum: TemplateVariableSource::class, example: 'recipient')]
#[BodyParam('variables[].default_field', 'string', 'Default recipient field; required when `default_source` is `recipient`.', required: false, nullable: true, enum: RecipientField::class, example: 'first_name')]
#[BodyParam('is_active', 'boolean', 'Usable by campaigns. Default true.', required: false, example: true)]
class WhatsAppTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('whatsapp_template');

        return $template instanceof WhatsAppTemplate
            ? Gate::forUser($this->user())->allows('update', $template)
            : Gate::forUser($this->user())->allows('create', WhatsAppTemplate::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $template = $this->route('whatsapp_template');
        $presence = $template instanceof WhatsAppTemplate ? 'sometimes' : 'required';
        $language = $this->input('language', $template?->language);

        return [
            'name' => [$presence, 'string', 'max:255'],
            /** The template name exactly as approved in Voxa / Meta. */
            'provider_name' => [
                $presence, 'string', 'max:255', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('whatsapp_templates', 'provider_name')
                    ->where('language', $language)
                    ->ignore($template?->getKey())
                    ->withoutTrashed(),
            ],
            'language' => [$presence, Rule::in(['ar', 'en'])],
            /** Preview of the approved text with `{{1}}`, `{{2}}` … placeholders. */
            'body' => ['nullable', 'string', 'max:4096'],
            /** Ordered variables, one per placeholder. */
            'variables' => [$presence, 'array', 'max:20'],
            'variables.*.label' => ['required', 'string', 'max:100'],
            'variables.*.default_source' => ['nullable', Rule::enum(TemplateVariableSource::class)],
            'variables.*.default_field' => ['nullable', 'required_if:variables.*.default_source,recipient', Rule::enum(RecipientField::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
