<?php

namespace Modules\Services\Http\Controllers\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Services\Http\Resources\Center\ServiceTypeCountResource;
use Modules\Services\Models\Service;
use Modules\Services\Services\ServiceTypeCountService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Service type counts', 'How many services a center has per type. Served on `centers/{center}/…` (center-scoped token; center users need `a `services.*.manage` permission`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class ServiceTypeCountController extends Controller
{
    public function __construct(private readonly ServiceTypeCountService $service) {}

    #[Endpoint('Count services per type', 'One entry per service type (except membership); `count` is 0 when none.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(ServiceTypeCountResource::class, Service::class, status: 200, description: 'One count per type.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not allowed to manage this center\'s services. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        return ServiceTypeCountResource::collection($this->service->countsForCenter($center));
    }
}
