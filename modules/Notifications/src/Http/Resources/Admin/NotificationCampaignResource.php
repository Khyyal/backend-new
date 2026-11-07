<?php

namespace Modules\Notifications\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Enums\CampaignAudience;
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
            /** The selected targets (clients or centers depending on `audience`) with their display name; `name` is null when the record no longer exists. @var list<array{id: int, name: string|null}> */
            'targets' => $this->whenLoaded('targets', fn (): array => $this->resolveTargets()),
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

    /**
     * @return list<array{id: int, name: string|null}>
     */
    private function resolveTargets(): array
    {
        $ids = $this->targets->pluck('target_id')->map(fn ($id): int => (int) $id);

        if ($ids->isEmpty() || $this->audience === null) {
            return [];
        }

        $model = $this->audience->targetModel();
        $query = $model::query();

        if (method_exists($model, 'withTrashed')) {
            $query->withTrashed();
        }

        $names = $query->whereKey($ids->all())->get()->mapWithKeys(fn ($target): array => [
            $target->getKey() => $this->audience === CampaignAudience::Clients ? ($target->full_name ?: null) : $target->name,
        ]);

        return $ids->map(fn (int $id): array => ['id' => $id, 'name' => $names[$id] ?? null])->all();
    }
}
