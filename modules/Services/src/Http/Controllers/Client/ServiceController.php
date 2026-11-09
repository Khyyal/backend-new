<?php

namespace Modules\Services\Http\Controllers\Client;

use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Reservations\Services\Availability\UpcomingAvailabilityService;
use Modules\Services\Http\Resources\Client\ServiceDetailsResource;
use Modules\Services\Models\Service;
use Modules\Services\Services\ServiceDetailsService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Services', 'Browse services. Public: no token needed. Names follow `Accept-Language`.')]
class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceDetailsService $service,
        private readonly UpcomingAvailabilityService $availability,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Show a service', 'Only active services of visible centers. `details` has the type-specific shape; recreation riding and events include `availability`, training includes it per trainer (today and the next 14 days).')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[UrlParam('slug', 'string', 'Service slug (unique per center).', example: 'beginner-ride')]
    #[ResponseFromApiResource(ServiceDetailsResource::class, Service::class, status: 200, description: 'The service.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such active service in this visible center.')]
    public function show(int $center, string $slug): ServiceDetailsResource
    {
        $service = $this->service->findBySlugForCenter($center, $slug);

        return ServiceDetailsResource::make($service)
            ->withAvailability($this->availability->forService($service));
    }
}
