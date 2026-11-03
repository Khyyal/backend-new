<?php

namespace Modules\Notifications\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Models\NotificationCampaign;

/**
 * @mixin NotificationCampaign
 */
class NotificationCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'audience' => $this->audience,
            'target_mode' => $this->target_mode,
            'status' => $this->status,
            /** @var array<string, string>|null */
            'title' => $this->getTranslations('title') ?: null,
            /** @var array<string, string>|null */
            'body' => $this->getTranslations('body') ?: null,
            'action_type' => $this->action_type,
            'action_target_id' => $this->action_target_id,
            'action_url' => $this->action_url,
            'whatsapp_template_id' => $this->whatsapp_template_id,
            'template' => WhatsAppTemplateResource::make($this->whenLoaded('template')),
            /** @var array<int, array{source: string, value?: string|null, field?: string|null}>|null */
            'template_parameters' => $this->template_parameters,
            /** @var list<int> */
            'target_ids' => $this->whenLoaded('targets', fn (): array => $this->targets->pluck('target_id')->map(fn ($id): int => (int) $id)->all()),
            'scheduled_at' => $this->scheduled_at,
            'sent_at' => $this->sent_at,
            'recipients_count' => $this->recipients_count,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
