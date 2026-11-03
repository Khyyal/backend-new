<?php

namespace Modules\Notifications\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Modules\Centers\Models\Center;
use Modules\Notifications\Enums\CampaignAudience;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Senders\CampaignRecipient;
use Modules\Notifications\Senders\Contracts\CampaignChannelSender;
use Modules\Notifications\Senders\PushCampaignSender;
use Modules\Notifications\Senders\WhatsAppCampaignSender;
use Modules\Notifications\Services\CampaignService;

class SendCampaignChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    /** Real failures (not rate-limit releases) before the chunk, and so the batch, fails. */
    public int $maxExceptions = 2;

    /**
     * @param  list<int>  $targetIds  Client ids or center ids, depending on the campaign audience.
     * @param  bool  $rateLimited  WhatsApp chunks are paced by the `voxa` limiter.
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly array $targetIds,
        public readonly bool $rateLimited = false,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return $this->rateLimited ? [new RateLimited('voxa')] : [];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(CampaignService $campaigns): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $campaign = NotificationCampaign::query()->with('template')->find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $targets = $campaigns->targetsQuery($campaign)->whereKey($this->targetIds)->get();

        $recipients = $campaign->audience === CampaignAudience::Centers
            ? $targets->flatMap(fn (Center $center) => $center->notifiableUsers()
                ->map(fn ($user): CampaignRecipient => new CampaignRecipient($user, $center)))->all()
            : $targets->map(fn ($client): CampaignRecipient => new CampaignRecipient($client))->all();

        $result = $this->sender($campaign)->send($campaign, $recipients);

        NotificationCampaign::query()->whereKey($this->campaignId)->incrementEach([
            'recipients_count' => count($recipients),
            'sent_count' => $result['sent'],
            'failed_count' => $result['failed'],
        ]);
    }

    private function sender(NotificationCampaign $campaign): CampaignChannelSender
    {
        return app(match ($campaign->channel) {
            CampaignChannel::Push => PushCampaignSender::class,
            CampaignChannel::WhatsApp => WhatsAppCampaignSender::class,
        });
    }
}
