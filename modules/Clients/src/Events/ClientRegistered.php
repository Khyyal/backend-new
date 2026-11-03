<?php

namespace Modules\Clients\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Clients\Models\Client;

class ClientRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Client $client,
    ) {}
}
