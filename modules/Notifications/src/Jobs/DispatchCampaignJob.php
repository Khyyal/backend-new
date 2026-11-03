<?php

namespace Modules\Notifications\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Services\CampaignService;
use Throwable;

/**
 * Splits a claimed campaign's targets into a batch of chunk jobs and settles the campaign status when it finishes.
 */
class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const int PUSH_CHUNK_SIZE = 500;

    /** Small WhatsApp chunks keep each job short and let the `voxa` rate limiter pace them. */
    public const int WHATSAPP_CHUNK_SIZE = 50;

    public function __construct(
        public readonly int $campaignId,
    ) {}

    public function handle(CampaignService $campaigns): void
    {
        $campaign = NotificationCampaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status !== CampaignStatus::Sending) {
            return;
        }

        $isWhatsApp = $campaign->channel === CampaignChannel::WhatsApp;
        $chunks = [];

        $campaigns->targetsQuery($campaign)
            ->select('id')
            ->chunkById($isWhatsApp ? self::WHATSAPP_CHUNK_SIZE : self::PUSH_CHUNK_SIZE, function (Collection $targets) use (&$chunks, $isWhatsApp): void {
                $chunks[] = new SendCampaignChunkJob($this->campaignId, $targets->modelKeys(), $isWhatsApp);
            });

        if ($chunks === []) {
            self::markFinished($this->campaignId, CampaignStatus::Sent);

            return;
        }

        $campaignId = $this->campaignId;

        Bus::batch($chunks)
            ->name("notification-campaign:{$campaignId}")
            ->then(static fn (Batch $batch) => self::markFinished($campaignId, CampaignStatus::Sent))
            ->catch(static fn (Batch $batch, Throwable $exception) => self::markFinished($campaignId, CampaignStatus::Failed))
            ->dispatch();
    }

    public function failed(?Throwable $exception): void
    {
        self::markFinished($this->campaignId, CampaignStatus::Failed);
    }

    public static function markFinished(int $campaignId, CampaignStatus $status): void
    {
        NotificationCampaign::query()
            ->whereKey($campaignId)
            ->where('status', CampaignStatus::Sending->value)
            ->update([
                'status' => $status->value,
                'sent_at' => $status === CampaignStatus::Sent ? now() : null,
                'updated_at' => now(),
            ]);
    }
}
