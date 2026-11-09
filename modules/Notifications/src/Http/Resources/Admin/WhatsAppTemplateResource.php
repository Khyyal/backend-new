<?php

namespace Modules\Notifications\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Notifications\Models\WhatsAppTemplate;

/**
 * @mixin WhatsAppTemplate
 */
class WhatsAppTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Template ID.', example: 4)]
    #[ResponseField('name', 'string', 'Internal name.', example: 'Booking reminder')]
    #[ResponseField('provider_name', 'string', 'Template name approved in Voxa / Meta.', example: 'booking_reminder')]
    #[ResponseField('language', 'string', 'Template language.', enum: ['ar', 'en'], example: 'ar')]
    #[ResponseField('body', 'string', 'Template text with `{{1}}`, `{{2}}`… placeholders, for preview.', nullable: true, example: 'مرحبا {{1}}، نذكرك بموعدك في {{2}}.')]
    #[ResponseField('variables', 'object[]', 'Placeholders in order: `label`, `default_source` (`fixed`/`recipient`/null), `default_field` (recipient field when `recipient`).', example: [['label' => 'Client name', 'default_source' => 'recipient', 'default_field' => 'first_name'], ['label' => 'Center', 'default_source' => 'fixed', 'default_field' => null]])]
    #[ResponseField('is_active', 'boolean', 'Only active templates can be used by campaigns.', example: true)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-06-01T08:00:00.000000Z')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-06-01T08:00:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider_name' => $this->provider_name,
            'language' => $this->language,
            'body' => $this->body,
            /** @var array<int, array{label: string, default_source: string|null, default_field: string|null}> */
            'variables' => $this->variables,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
