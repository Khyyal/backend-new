<?php

namespace Modules\Services\Concerns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Services\Models\Service;

trait HasService
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function priceOptions(): HasMany
    {
        return $this->service->priceOptions();
    }
}
