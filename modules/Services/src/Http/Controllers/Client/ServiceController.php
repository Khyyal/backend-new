<?php

namespace Modules\Services\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Routing\Controller;
use Modules\Services\Http\Resources\Client\ServiceDetailsResource;
use Modules\Services\Services\ServiceDetailsService;

#[Group(name: 'Client / Services', description: 'Browse the services of a center. Public, no authentication required.')]
class ServiceController extends Controller
{
    public function __construct(private readonly ServiceDetailsService $service) {}

    /**
     * Show a service by its slug.
     *
     * Slugs are unique per center. Only `active` services of `visible`
     * centers are returned. `type` is the service type and `details` its
     * type-specific shape (e.g. `days` for resorts, `trainers` for training,
     * `days` and `hours` for recreation riding and visits).
     */
    #[Response(status: 200, description: '`{data: {type, details}}`.')]
    #[Response(status: 404, description: 'No such active service in this visible center.')]
    public function show(int $center, string $slug): ServiceDetailsResource
    {
        return ServiceDetailsResource::make($this->service->findBySlugForCenter($center, $slug));
    }
}
