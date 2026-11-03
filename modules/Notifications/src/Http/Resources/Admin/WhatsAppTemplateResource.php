<?php

namespace Modules\Notifications\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Models\WhatsAppTemplate;

/**
 * @mixin WhatsAppTemplate
 */
class WhatsAppTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
