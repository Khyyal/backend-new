<?php

namespace Modules\Services\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Services\Http\Resources\Center\ServiceTypeGroupResource;
use Modules\Services\Services\ServiceListService;

#[Group(name: 'Center / Service List', description: 'Lightweight lists of a center\'s services. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ServiceListController extends Controller
{
    public function __construct(private readonly ServiceListService $service) {}

    /**
     * List the center's services grouped by service type.
     *
     * Not paginated. Always returns one entry per service type (`recreation_riding`, `visit`,
     * `event`, `resort`, `horse_care`, `horse_service`, `training`); `services` is empty for a
     * type the center has none of. Each service has only `id` and `name`.
     */
    #[Response(status: 200, description: '`ServiceTypeGroupResource` collection, one per service type: `service_type`, `services` [{id,name}].')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function byType(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        return ServiceTypeGroupResource::collection($this->service->groupedByType($center));
    }
}
