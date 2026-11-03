<?php

namespace Modules\Notifications\Console;

use Illuminate\Console\Command;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Services\CampaignService;

class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'notifications:dispatch-due-campaigns';

    protected $description = 'Send scheduled notification campaigns whose time has come';

    public function handle(CampaignService $campaigns): int
    {
        $dispatched = 0;

        NotificationCampaign::query()
            ->where('status', CampaignStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (NotificationCampaign $campaign) use ($campaigns, &$dispatched): void {
                if ($campaigns->sendNow($campaign)) {
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} campaign(s).");

        return self::SUCCESS;
    }
}
