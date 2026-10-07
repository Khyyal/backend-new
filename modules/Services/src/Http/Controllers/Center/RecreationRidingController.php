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
use Modules\Centers\Models\User;
use Modules\Services\Http\Requests\Center\RecreationRidingRequest;
use Modules\Services\Http\Resources\Center\RecreationRidingResource;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Services\CenterRecreationRidingService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Recreation Riding', description: 'Manage the recreation riding services of a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class RecreationRidingController extends Controller
{
    public function __construct(private readonly CenterRecreationRidingService $service) {}

    /**
     * List the center's recreation riding services.
     *
     * Paginated (15 per page, `?page=`), newest first. Each item is a
     * `RecreationRidingResource`.
     */
    #[Response(status: 200, description: 'Paginated `RecreationRidingResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $ridings = RecreationalRiding::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.media', 'schedules'])
            ->latest('id')
            ->paginate(15);

        return RecreationRidingResource::collection($ridings);
    }

    /**
     * Create a recreation riding service.
     *
     * **Request body:**
     * - `name`                    required object; `ar` required string, `en` optional string.
     * - `description`             required object; `ar` required string, `en` optional string.
     * - `price_options`           required array (1-20) of `{ duration, price }`; `duration` is minutes (integer >= 1), `price` a number >= 0.
     * - `days`                    required array of distinct weekday numbers `0`-`6`.
     * - `hours`                   required flat list of `HH:mm` start/end pairs, e.g. `["09:00","12:00","14:00","17:00"]` (two slots); applied to every day in `days`. Each end must be after its start.
     * - `cover`                   optional media id (uuid) or `null`.
     * - `images`                  optional array (max 10) of media ids, in display order.
     *
     * Media ids come from the Support media API (`POST /media`); temporary
     * uploads must belong to the authenticated user and not be expired.
     *
     * The service is created `active`.
     */
    #[Response(status: 201, description: 'The created service as a `RecreationRidingResource`: `id`, `service_id`, `center_id`, `slug`, `status`, `name` {ar,en}, `description` {ar,en}, `price_options` [{id,duration,price}], `days`, `hours`, `cover`, `images`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function store(RecreationRidingRequest $request, Center $center): JsonResponse
    {
        $riding = $this->service->create($center, $this->user($request), $request->validated());

        return RecreationRidingResource::make($riding)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a recreation riding service.
     */
    #[Response(status: 200, description: 'The service as a `RecreationRidingResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function show(Request $request, Center $center, int $recreationRiding): RecreationRidingResource
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return RecreationRidingResource::make($this->service->findForCenter($center, $recreationRiding));
    }

    /**
     * Update a recreation riding service.
     *
     * Replaces the definition: `name`, `description`, `price_options`, `days`
     * and `hours` are all required and take the same shape as on create (old
     * price options and schedules are replaced). `cover` and `images` are
     * optional and untouched when omitted: `cover` takes a media id (`null`
     * removes it); `images` is the full list of media ids in display order, so
     * existing ids are kept, new temporary ids are attached and omitted ones
     * are removed.
     */
    #[Response(status: 200, description: 'The updated service as a `RecreationRidingResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function update(RecreationRidingRequest $request, Center $center, int $recreationRiding): RecreationRidingResource
    {
        $riding = $this->service->findForCenter($center, $recreationRiding);

        return RecreationRidingResource::make(
            $this->service->update($center, $riding, $this->user($request), $request->validated())
        );
    }

    /**
     * Delete a recreation riding service.
     *
     * Soft-deletes the service together with its price options and schedules.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function destroy(Request $request, Center $center, int $recreationRiding): \Illuminate\Http\Response
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $this->service->delete($this->service->findForCenter($center, $recreationRiding));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        return $request->user('center_user');
    }
}
