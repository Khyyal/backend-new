<?php

namespace Modules\Services\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Services\Http\Requests\Center\ServiceBlockRequest;
use Modules\Services\Http\Resources\Center\ServiceBlockResource;
use Modules\Services\Services\ServiceBlockService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Service Blocks', description: 'Block dates so a center\'s services (all of them, or one specific service) are not purchasable during them. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ServiceBlockController extends Controller
{
    public function __construct(private readonly ServiceBlockService $service) {}

    /**
     * List the center's blocked dates.
     *
     * Paginated (15 per page, `?page=`), newest first. Optionally filter to
     * one service with `?service_id=`. Each item is a `ServiceBlockResource`.
     */
    #[Response(status: 200, description: 'Paginated `ServiceBlockResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $blocks = $this->service->list($center, $request->integer('service_id') ?: null);

        return ServiceBlockResource::collection($blocks);
    }

    /**
     * Create a blocked date.
     *
     * **Request body:**
     * - `service_id`   optional integer; a service belonging to this center, or omitted/`null` to block all of the center's services.
     * - `reason`       required string (max 500).
     * - `start_date`   required date.
     * - `end_date`     required date, on or after `start_date` (equal to `start_date` for a single-day block).
     * - `time_scope`   required, `all_day` or `specific_time`.
     * - `start_time`   required `H:i` when `time_scope` is `specific_time`, otherwise must be omitted.
     * - `end_time`     required `H:i` (after `start_time`) when `time_scope` is `specific_time`, otherwise must be omitted.
     */
    #[Response(status: 201, description: 'The created block as a `ServiceBlockResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function store(ServiceBlockRequest $request, Center $center): JsonResponse
    {
        $block = $this->service->create($center, $request->validated());

        return ServiceBlockResource::make($block)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a blocked date.
     */
    #[Response(status: 200, description: 'The block as a `ServiceBlockResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such block in this center.')]
    public function show(Request $request, Center $center, int $serviceBlock): ServiceBlockResource
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return ServiceBlockResource::make($this->service->findForCenter($center, $serviceBlock));
    }

    /**
     * Update a blocked date.
     *
     * Replaces the definition; takes the same body as create.
     */
    #[Response(status: 200, description: 'The updated block as a `ServiceBlockResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such block in this center.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function update(ServiceBlockRequest $request, Center $center, int $serviceBlock): ServiceBlockResource
    {
        $block = $this->service->findForCenter($center, $serviceBlock);

        return ServiceBlockResource::make($this->service->update($block, $request->validated()));
    }

    /**
     * Delete a blocked date.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such block in this center.')]
    public function destroy(Request $request, Center $center, int $serviceBlock): \Illuminate\Http\Response
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $this->service->delete($this->service->findForCenter($center, $serviceBlock));

        return response()->noContent();
    }
}
