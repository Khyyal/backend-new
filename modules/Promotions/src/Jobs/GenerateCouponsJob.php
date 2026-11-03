<?php

namespace Modules\Promotions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\CouponGenerator;

class GenerateCouponsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly Promotion $promotion,
        public readonly int $count,
        public readonly string $prefix,
        public readonly int $length,
        public readonly string $batchId,
        public readonly array $options = [],
        public readonly ?Model $creator = null,
    ) {}

    public function handle(CouponGenerator $generator): void
    {
        $generator->generate($this->promotion, $this->count, $this->prefix, $this->length, $this->batchId, $this->options, $this->creator);
    }
}
