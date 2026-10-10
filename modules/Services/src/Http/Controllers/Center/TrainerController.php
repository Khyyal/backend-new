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
use Modules\Services\Http\Requests\Center\TrainerRequest;
use Modules\Services\Http\Resources\Center\TrainerResource;
use Modules\Services\Services\TrainerService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Trainers', description: 'Manage the trainers of a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class TrainerController extends Controller
{
    public function __construct(private readonly TrainerService $service) {}

    /**
     * List the center's trainers.
     *
     * Paginated (15 per page, `?page=`), newest first. Each item is a
     * `TrainerResource`.
     */
    #[Response(status: 200, description: 'Paginated `TrainerResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return TrainerResource::collection($this->service->list($center));
    }

    /**
     * Create a trainer.
     *
     * **Request body:**
     * - `name`                    required string.
     * - `max_tickets_per_hour`    optional integer >= 1, or `null`/omitted for no hourly limit.
     * - `days`                    required array of distinct weekday numbers `0`-`6`.
     * - `hours`                   required flat list of `HH:mm` start/end pairs, e.g. `["09:00","12:00","14:00","17:00"]` (two slots); applied to every day in `days`. Each end must be after its start.
     * - `training_service_ids`    optional array of training service ids of this center the trainer works in.
     */
    #[Response(status: 201, description: 'The created trainer as a `TrainerResource`: `id`, `center_id`, `name`, `max_tickets_per_hour`, `days`, `hours`, `training_services` [{id,name}].')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function store(TrainerRequest $request, Center $center): JsonResponse
    {
        $trainer = $this->service->create($center, $request->validated());

        return TrainerResource::make($trainer)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a trainer.
     */
    #[Response(status: 200, description: 'The trainer as a `TrainerResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such trainer in this center.')]
    public function show(Request $request, Center $center, int $trainer): TrainerResource
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return TrainerResource::make($this->service->findForCenter($center, $trainer));
    }

    /**
     * Update a trainer.
     *
     * Replaces the definition: `name`, `days` and `hours` are required
     * (`max_tickets_per_hour` is optional and resets to `null` when omitted)
     * and take the same shape as on create (old schedules are replaced).
     * `training_service_ids` is optional and untouched when omitted; when
     * sent it is the full list of training services.
     */
    #[Response(status: 200, description: 'The updated trainer as a `TrainerResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such trainer in this center.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function update(TrainerRequest $request, Center $center, int $trainer): TrainerResource
    {
        $model = $this->service->findForCenter($center, $trainer);

        return TrainerResource::make($this->service->update($model, $request->validated()));
    }

    /**
     * Delete a trainer.
     *
     * Soft-deletes the trainer together with its schedules and detaches it
     * from its training services.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such trainer in this center.')]
    public function destroy(Request $request, Center $center, int $trainer): \Illuminate\Http\Response
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $this->service->delete($this->service->findForCenter($center, $trainer));

        return response()->noContent();
    }
}
