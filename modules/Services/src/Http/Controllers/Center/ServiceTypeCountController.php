<?php

namespace Modules\Services\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Services\Http\Resources\Center\ServiceTypeCountResource;
use Modules\Services\Services\ServiceTypeCountService;

#[Group(name: 'Center / Service Type Counts', description: 'Count a center\'s services per service type. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ServiceTypeCountController extends Controller
{
    public function __construct(private readonly ServiceTypeCountService $service) {}

    /**
     * List every service type with how many services this center has of it.
     *
     * Always returns one entry per service type (`recreation_riding`, `visit`,
     * `event`, `resort`, `horse_care`, `horse_service`); `count` is `0` for a
     * type the center has none of.
     */
    #[Response(status: 200, description: '`ServiceTypeCountResource` collection, one per service type: `service_type`, `count`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return ServiceTypeCountResource::collection($this->service->countsForCenter($center));
    }
}
