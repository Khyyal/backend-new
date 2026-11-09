<?php

namespace Modules\Reservations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Reservations\Models\ClientMembership;

class ClientMembershipExpired
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ClientMembership $clientMembership,
    ) {}
}
