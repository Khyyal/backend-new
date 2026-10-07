<?php

namespace Modules\Support\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Support\Services\Media\TemporaryMediaService;

class CleanupExpiredMedia implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(TemporaryMediaService $temporaryMedia): void
    {
        $temporaryMedia->cleanupExpired();
    }
}
