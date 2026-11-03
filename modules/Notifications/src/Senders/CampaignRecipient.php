<?php

namespace Modules\Notifications\Senders;

use Illuminate\Database\Eloquent\Model;
use Modules\Centers\Models\Center;

/**
 * A notifiable person reached by a campaign; `$center` is set when they are reached as staff of a targeted center.
 */
final readonly class CampaignRecipient
{
    public function __construct(
        public Model $notifiable,
        public ?Center $center = null,
    ) {}
}
