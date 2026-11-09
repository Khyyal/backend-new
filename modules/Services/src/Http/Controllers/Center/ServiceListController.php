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
use Modules\Services\Http\Resources\Center\ServiceTypeGroupResource;
use Modules\Services\Models\Service;
use Modules\Services\Services\ServiceListService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Service list', 'Lightweight service lists for pickers. Served on `centers/{center}/…` (center-scoped token; center users need `a `services.*.manage` permission`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class ServiceListController extends Controller
{
    public function __construct(private readonly ServiceListService $service) {}

    #[Endpoint('List services grouped by type', 'One entry per service type (except membership); `services` is empty for a type the center has none of. Not paginated.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(ServiceTypeGroupResource::class, Service::class, status: 200, description: 'One group per type.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not allowed to manage this center\'s services. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function byType(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        return ServiceTypeGroupResource::collection($this->service->groupedByType($center));
    }
}
