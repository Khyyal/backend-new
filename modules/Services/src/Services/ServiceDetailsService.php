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
     * Relations of the Service itself, per serviceable type.
     *
     * @var array<class-string, list<string>>
     */
    private const SERVICE_RELATIONS = [
        RecreationalRiding::class => ['priceOptions.package', 'settings', 'media'],
        Visit::class => ['priceOptions', 'settings', 'media'],
        Event::class => ['priceOptions', 'settings', 'media'],
        Resort::class => ['priceOptions', 'settings', 'media'],
        HorseCare::class => ['priceOptions', 'media'],
        HorseService::class => ['priceOptions', 'media'],
        TrainingService::class => ['priceOptions.package', 'settings', 'media'],
    ];

    /**
     * Relations of the serviceable, per type.
     *
     * @var array<class-string, list<string>>
     */
    private const SERVICEABLE_RELATIONS = [
        RecreationalRiding::class => ['schedules'],
        Visit::class => ['schedules'],
        TrainingService::class => ['trainers'],
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

        $service->load(self::SERVICE_RELATIONS[$serviceable::class] ?? []);
        $serviceable->setRelation('service', $service)
            ->load(self::SERVICEABLE_RELATIONS[$serviceable::class] ?? []);

        return $service;
    }
}
