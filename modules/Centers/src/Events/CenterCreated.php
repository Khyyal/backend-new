<?php

namespace Modules\Centers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;

/**
 * A center was created, by self-registration or by an admin.
 */
class CenterCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Center $center,
        public readonly User $owner,
    ) {}
}
