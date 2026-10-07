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
use Modules\Services\Http\Requests\Center\VisitRequest;
use Modules\Services\Http\Resources\Center\VisitResource;
use Modules\Services\Models\Visit;
use Modules\Services\Services\CenterVisitService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Visit', description: 'Manage the visit services of a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class VisitController extends Controller
{
    public function __construct(private readonly CenterVisitService $service) {}

    /**
     * List the center's visit services.
     *
     * Paginated (15 per page, `?page=`), newest first. Each item is a
     * `VisitResource`.
     */
    #[Response(status: 200, description: 'Paginated `VisitResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $visits = Visit::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.media', 'schedules'])
            ->latest('id')
            ->paginate(15);

        return VisitResource::collection($visits);
    }

    /**
     * Create a visit service.
     *
     * **Request body:**
     * - `name`                    required object; `ar` required string, `en` optional string.
     * - `description`             required object; `ar` required string, `en` optional string.
     * - `enter_type`              required `all_day` or `specific_time`.
     * - `price`                   required number >= 0.
     * - `days`                    required array of distinct weekday numbers `0`-`6`.
     * - `hours`                   required flat list of `HH:mm` start/end pairs when `enter_type` is `specific_time`
     *                             (applied to every day in `days`); must be omitted when `enter_type` is `all_day`.
     * - `cover`                   optional media id (uuid) or `null`.
     * - `images`                  optional array (max 10) of media ids, in display order.
     *
     * Media ids come from the Support media API (`POST /media`); temporary
     * uploads must belong to the authenticated user and not be expired.
     *
     * The service is created `active`.
     */
    #[Response(status: 201, description: 'The created service as a `VisitResource`: `id`, `service_id`, `center_id`, `slug`, `status`, `name` {ar,en}, `description` {ar,en}, `enter_type`, `price`, `days`, `hours`, `cover`, `images`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function store(VisitRequest $request, Center $center): JsonResponse
    {
        $visit = $this->service->create($center, $this->user($request), $request->validated());

        return VisitResource::make($visit)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a visit service.
     */
    #[Response(status: 200, description: 'The service as a `VisitResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function show(Request $request, Center $center, int $visit): VisitResource
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return VisitResource::make($this->service->findForCenter($center, $visit));
    }

    /**
     * Update a visit service.
     *
     * Replaces the definition: `name`, `description`, `enter_type`, `price`,
     * `days` (and `hours`, when applicable) are all required and take the same
     * shape as on create (old price option and schedules are replaced).
     * `cover` and `images` are optional and untouched when omitted: `cover`
     * takes a media id (`null` removes it); `images` is the full list of media
     * ids in display order, so existing ids are kept, new temporary ids are
     * attached and omitted ones are removed.
     */
    #[Response(status: 200, description: 'The updated service as a `VisitResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function update(VisitRequest $request, Center $center, int $visit): VisitResource
    {
        $model = $this->service->findForCenter($center, $visit);

        return VisitResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    /**
     * Delete a visit service.
     *
     * Soft-deletes the service together with its price option and schedules.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function destroy(Request $request, Center $center, int $visit): \Illuminate\Http\Response
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $this->service->delete($this->service->findForCenter($center, $visit));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        return $request->user('center_user');
    }
}
