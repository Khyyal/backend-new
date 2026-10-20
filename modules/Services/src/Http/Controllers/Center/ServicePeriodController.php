<?php

namespace Modules\Services\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Services\Http\Requests\Center\ServicePeriodRequest;
use Modules\Services\Http\Resources\Center\ServicePeriodResource;
use Modules\Services\Services\ServicePeriodService;

#[Group(name: 'Center / Service Periods', description: 'Female and male periods of a service: the days of the week and hours reserved for each gender. A center may define one, both or neither. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ServicePeriodController extends Controller
{
    public function __construct(private readonly ServicePeriodService $periods) {}

    /**
     * Show a service's periods.
     *
     * Returns `female` and `male`, each `null` when not defined, otherwise `{days, hours}`.
     */
    #[Response(status: 200, description: 'The periods as a `ServicePeriodResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function show(Request $request, Center $center, int $service): ServicePeriodResource
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        $service = $this->periods->findServiceForCenter($center, $service)->load('periods');

        return ServicePeriodResource::make($service);
    }

    /**
     * Replace a service's periods.
     *
     * **Request body:**
     * - `female` / `male`  optional object or `null` (clears that gender's period).
     * - `days`             required integer array (0-6, distinct) within a gender object.
     * - `hours`            required flat list of `H:i` start/end pairs within a gender object, e.g. `["09:00","12:00","16:00","18:00"]`; each end is after its start.
     */
    #[Response(status: 200, description: 'The updated periods as a `ServicePeriodResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function update(ServicePeriodRequest $request, Center $center, int $service): ServicePeriodResource
    {
        $service = $this->periods->findServiceForCenter($center, $service);

        return ServicePeriodResource::make($this->periods->sync($service, $request->validated()));
    }
}
