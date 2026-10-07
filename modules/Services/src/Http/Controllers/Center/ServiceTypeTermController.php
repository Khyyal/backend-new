<?php

namespace Modules\Services\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Http\Requests\Center\ServiceTypeTermRequest;
use Modules\Services\Http\Resources\Center\ServiceTypeTermResource;
use Modules\Services\Services\ServiceTypeTermService;

#[Group(name: 'Center / Service Type Terms', description: "Manage each service type's terms and conditions for a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).")]
class ServiceTypeTermController extends Controller
{
    public function __construct(private readonly ServiceTypeTermService $service) {}

    /**
     * List every service type with its terms and conditions for this center.
     *
     * Always returns one entry per service type (`recreation_riding`, `visit`,
     * `event`, `resort`); `terms` is `null` for a type that has none defined yet.
     */
    #[Response(status: 200, description: '`ServiceTypeTermResource` collection, one per service type.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return ServiceTypeTermResource::collection($this->service->listForCenter($center));
    }

    /**
     * Add or replace the terms and conditions for a service type.
     *
     * **Request body:**
     * - `terms` required object; `ar` required string, `en` optional string.
     *
     * Upserts: calling it again for the same type replaces the existing terms.
     */
    #[Response(status: 201, description: 'Terms created for a type that had none yet, as a `ServiceTypeTermResource`.')]
    #[Response(status: 200, description: 'Existing terms for that type were replaced, as a `ServiceTypeTermResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: '`type` is not a valid service type.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function updateTerms(ServiceTypeTermRequest $request, Center $center, ServiceType $type): ServiceTypeTermResource
    {
        return ServiceTypeTermResource::make(
            $this->service->upsert($center, $type, $request->validated())
        );
    }
}
