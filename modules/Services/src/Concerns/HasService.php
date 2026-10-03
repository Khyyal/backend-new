<?php

namespace Modules\Services\Concerns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;

trait HasService
{
    public function service(): MorphOne
    {
        return $this->morphOne(Service::class, 'serviceable');
    }

    public function priceOptions(): HasManyThrough
    {
        return $this->hasManyThrough(
            PriceOption::class,
            Service::class,
            'serviceable_id',
            'service_id',
        )->where('services.serviceable_type', $this->getMorphClass());
    }
}
