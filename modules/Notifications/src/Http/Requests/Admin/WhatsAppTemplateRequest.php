<?php

namespace Modules\Notifications\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Notifications\Enums\RecipientField;
use Modules\Notifications\Enums\TemplateVariableSource;
use Modules\Notifications\Models\WhatsAppTemplate;

/**
 * Create (POST) or update (PUT/PATCH) a WhatsApp template.
 */
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
