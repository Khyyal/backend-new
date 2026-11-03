<?php

namespace Modules\Notifications\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Notifications\Enums\CampaignActionType;
use Modules\Notifications\Enums\CampaignAudience;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Enums\CampaignTargetMode;
use Modules\Notifications\Jobs\DispatchCampaignJob;
use Modules\Notifications\Models\NotificationCampaign;

class CampaignService
{
    /**
     * @param  array<string, mixed>  $attributes  Validated campaign fields, optionally with target_ids.
     */
    public function create(array $attributes, ?int $createdBy): NotificationCampaign
    {
        return DB::transaction(function () use ($attributes, $createdBy): NotificationCampaign {
            $campaign = NotificationCampaign::create([
                ...$this->campaignFields($attributes),
                ...$this->clearOtherChannel(CampaignChannel::from($attributes['channel'])),
                'status' => empty($attributes['scheduled_at']) ? CampaignStatus::Draft : CampaignStatus::Scheduled,
                'created_by' => $createdBy,
            ]);

            $this->syncTargets($campaign, $attributes['target_ids'] ?? []);

            return $campaign;
        });
    }

    /**
     * Copies content, audience and targets into a new draft; delivery state and schedule are not carried over.
     */
    public function duplicate(NotificationCampaign $campaign, ?int $createdBy): NotificationCampaign
    {
        return DB::transaction(function () use ($campaign, $createdBy): NotificationCampaign {
            $copy = NotificationCampaign::create([
                ...$campaign->only([
                    'channel', 'audience', 'target_mode', 'action_type', 'action_target_id', 'action_url',
                    'whatsapp_template_id', 'template_parameters',
                ]),
                'name' => $campaign->name.' (copy)',
                'title' => $campaign->getTranslations('title'),
                'body' => $campaign->getTranslations('body'),
                'status' => CampaignStatus::Draft,
                'created_by' => $createdBy,
            ]);

            $this->syncTargets($copy, $campaign->targetIds());

            return $copy;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(NotificationCampaign $campaign, array $attributes): NotificationCampaign
    {
        $this->ensureEditable($campaign);

        return DB::transaction(function () use ($campaign, $attributes): NotificationCampaign {
            $fields = $this->campaignFields($attributes);

            if (array_key_exists('scheduled_at', $attributes)) {
                $fields['status'] = $attributes['scheduled_at'] === null ? CampaignStatus::Draft : CampaignStatus::Scheduled;
            }

            $campaign->fill($fields);
            $campaign->fill($this->clearOtherChannel($campaign->channel));

            if ($campaign->channel === CampaignChannel::WhatsApp) {
                $campaign->forgetTranslations('title', asNull: true)->forgetTranslations('body', asNull: true);
            }

            $campaign->save();

            if ($campaign->target_mode === CampaignTargetMode::All) {
                $campaign->targets()->delete();
            } elseif (array_key_exists('target_ids', $attributes)) {
                $this->syncTargets($campaign, $attributes['target_ids']);
            }

            return $campaign;
        });
    }

    public function schedule(NotificationCampaign $campaign, DateTimeInterface $at): NotificationCampaign
    {
        $this->ensureEditable($campaign);

        $campaign->update(['status' => CampaignStatus::Scheduled, 'scheduled_at' => $at]);

        return $campaign;
    }

    public function cancel(NotificationCampaign $campaign): NotificationCampaign
    {
        $this->ensureEditable($campaign);

        $campaign->update(['status' => CampaignStatus::Cancelled]);

        return $campaign;
    }

    /**
     * Claims the campaign for sending and queues its dispatch. Returns false when another caller
     * already claimed it, so a campaign is never sent twice.
     */
    public function sendNow(NotificationCampaign $campaign): bool
    {
        $claimed = NotificationCampaign::query()
            ->whereKey($campaign->getKey())
            ->whereIn('status', [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value])
            ->update(['status' => CampaignStatus::Sending->value, 'updated_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        DispatchCampaignJob::dispatch($campaign->getKey());

        $campaign->refresh();

        return true;
    }

    /**
     * The campaign targets: clients, or centers whose active staff receive it.
     *
     * @return Builder<Client>|Builder<Center>
     */
    public function targetsQuery(NotificationCampaign $campaign): Builder
    {
        $targetIds = $campaign->target_mode === CampaignTargetMode::Selected ? $campaign->targetIds() : null;

        return match ($campaign->audience) {
            CampaignAudience::Clients => Client::query()
                ->when($campaign->channel === CampaignChannel::WhatsApp, fn (Builder $query) => $query->whereNotNull('phone_number'))
                ->when($targetIds !== null, fn (Builder $query) => $query->whereKey($targetIds)),
            CampaignAudience::Centers => Center::query()
                ->when($targetIds !== null, fn (Builder $query) => $query->whereKey($targetIds)),
        };
    }

    public function ensureEditable(NotificationCampaign $campaign): void
    {
        if (! $campaign->status->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('notifications::campaigns.not_editable'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function campaignFields(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip([
            'name', 'channel', 'audience', 'target_mode', 'title', 'body', 'action_type', 'action_target_id', 'action_url',
            'whatsapp_template_id', 'template_parameters', 'scheduled_at',
        ]));
    }

    /**
     * A campaign only keeps the content of its own channel.
     *
     * @return array<string, mixed>
     */
    private function clearOtherChannel(CampaignChannel $channel): array
    {
        return $channel === CampaignChannel::Push
            ? ['whatsapp_template_id' => null, 'template_parameters' => null]
            : ['action_type' => CampaignActionType::None, 'action_target_id' => null, 'action_url' => null];
    }

    /**
     * @param  array<int, int|string>  $targetIds
     */
    private function syncTargets(NotificationCampaign $campaign, array $targetIds): void
    {
        $campaign->targets()->delete();

        $campaign->targets()->createMany(array_map(
            fn (int $id): array => ['target_id' => $id],
            array_values(array_unique(array_map('intval', $targetIds))),
        ));
    }
}
