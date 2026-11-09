<?php

namespace Modules\Notifications\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Notifications\Enums\CampaignActionType;
use Modules\Notifications\Enums\CampaignAudience;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Enums\CampaignTargetMode;
use Modules\Notifications\Models\NotificationCampaign;

/**
 * @mixin NotificationCampaign
 */
class NotificationCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Campaign ID.', example: 15)]
    #[ResponseField('name', 'string', 'Internal name.', example: 'Weekend promo')]
    #[ResponseField('channel', 'string', 'Delivery channel.', enum: CampaignChannel::class, example: 'push')]
    #[ResponseField('audience', 'string', '`clients` or center staff (`centers`).', enum: CampaignAudience::class, example: 'clients')]
    #[ResponseField('target_mode', 'string', 'Everyone in the audience, or the `targets` only.', enum: CampaignTargetMode::class, example: 'selected')]
    #[ResponseField('status', 'string', 'Lifecycle status.', enum: CampaignStatus::class, example: 'draft')]
    #[ResponseField('title', 'object', 'Push title keyed by locale; null for WhatsApp.', nullable: true, example: ['en' => 'Weekend rides', 'ar' => 'ركوب نهاية الأسبوع'])]
    #[ResponseField('body', 'object', 'Push body keyed by locale; null for WhatsApp.', nullable: true, example: ['en' => '15% off this weekend.', 'ar' => 'خصم 15% هذا الأسبوع.'])]
    #[ResponseField('action_type', 'string', 'Push tap action.', nullable: true, enum: CampaignActionType::class, example: 'center')]
    #[ResponseField('action_target_id', 'integer', 'Center or service ID for those actions.', nullable: true, example: 8)]
    #[ResponseField('action_url', 'string', 'URL for the `url` action.', nullable: true, example: 'No-example')]
    #[ResponseField('whatsapp_template_id', 'integer', 'WhatsApp template; null for push.', nullable: true, example: 'No-example')]
    #[ResponseField('template', 'object', 'The WhatsApp template (`WhatsAppTemplateResource`). Present when loaded.', required: false, nullable: true)]
    #[ResponseField('template_parameters', 'object[]', 'WhatsApp bindings in variable order: `{source: fixed, value}` or `{source: recipient, field}`; null for push.', nullable: true, example: 'No-example')]
    #[ResponseField('target_ids', 'integer[]', 'Selected client or center IDs. Present when loaded.', required: false, example: [77, 78])]
    #[ResponseField('targets', 'object[]', 'Selected targets with display name (`id`, `name`; `name` null when the record no longer exists). Present when loaded.', required: false, example: [['id' => 77, 'name' => 'Noura Al-Qahtani']])]
    #[ResponseField('scheduled_at', 'string', 'Scheduled send time (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('sent_at', 'string', 'When sending finished (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('recipients_count', 'integer', 'Recipients resolved when sending.', nullable: true, example: 0)]
    #[ResponseField('sent_count', 'integer', 'Successful sends.', nullable: true, example: 0)]
    #[ResponseField('failed_count', 'integer', 'Failed sends.', nullable: true, example: 0)]
    #[ResponseField('created_by', 'integer', 'System user who created it.', nullable: true, example: 2)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-06-01T08:00:00.000000Z')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-06-01T08:00:00.000000Z')]
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
