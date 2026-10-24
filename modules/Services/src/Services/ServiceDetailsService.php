<?php

namespace Modules\Services\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Services\Models\Event;
use Modules\Services\Models\HorseCare;
use Modules\Services\Models\HorseService;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
use Modules\Services\Models\TrainingService;
use Modules\Services\Models\Visit;
use Modules\Support\Enums\ActivationStatus;

/**
 * Client-facing lookup of an active service of a visible center by its slug,
 * with the serviceable loaded the way its type-specific resource needs it.
 */
class ServiceDetailsService
{
    /**
     * @var array<class-string, list<string>>
     */
    private const RELATIONS = [
        RecreationalRiding::class => ['service.priceOptions.package', 'service.settings', 'service.media', 'schedules'],
        Visit::class => ['service.priceOptions', 'service.settings', 'service.media', 'schedules'],
        Event::class => ['service.priceOptions', 'service.settings', 'service.media'],
        Resort::class => ['service.priceOptions', 'service.settings', 'service.media'],
        HorseCare::class => ['service.priceOptions', 'service.media'],
        HorseService::class => ['service.priceOptions', 'service.media'],
        TrainingService::class => ['service.priceOptions.package', 'service.settings', 'service.media', 'trainers'],
    ];

    /**
     * @throws ModelNotFoundException
     */
    public function findBySlugForCenter(int $center, string $slug): Service
    {
        $service = Service::query()
            ->where('slug', $slug)
            ->where('status', ActivationStatus::ACTIVE)
            ->whereHas('center', fn (Builder $query) => $query->visible()->whereKey($center))
            ->with('serviceable')
            ->firstOrFail();

        $serviceable = $service->serviceable ?? throw (new ModelNotFoundException)->setModel(Service::class);

        $serviceable->setRelation('service', $service)
            ->loadMissing(self::RELATIONS[$serviceable::class] ?? []);

        return $service;
    }
}
