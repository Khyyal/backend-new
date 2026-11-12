<?php

namespace Modules\Reservations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Reservations\Models\ClientPackage;

class ClientPackageCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ClientPackage $clientPackage,
    ) {}
}
