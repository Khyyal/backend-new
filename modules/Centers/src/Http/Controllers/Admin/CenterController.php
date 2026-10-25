<?php

namespace Modules\Centers\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Requests\Admin\ListCentersRequest;
use Modules\Centers\Http\Requests\Admin\StoreCenterRequest;
use Modules\Centers\Http\Requests\Admin\UpdateCenterRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAdminService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Centers', description: 'System user management of centers. Requires a system user Bearer token (see `POST /admin/auth/login`) with the matching `centers.*` permission.')]
class CenterController extends Controller
{
    public function __construct(private readonly CenterAdminService $service) {}

    /**
     * List centers.
     *
     * Paginated (`per_page`, default 15), newest first. Optional filters:
     * `search` (name or contact phone), `status` (`visible`/`invisible`) and
     * `city_id`.
     */
    #[Response(status: 200, description: 'Paginated `CenterResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid system user Bearer token.')]
    #[Response(status: 403, description: 'Missing the `centers.view_any` permission.')]
    public function index(ListCentersRequest $request): AnonymousResourceCollection
    {
        return CenterResource::collection(
            $this->service->paginate($request->validated(), $request->integer('per_page', 15))
        );
    }

    /**
     * Create a center.
     *
     * **Request body:**
     * - `name`, `city_id`, `contact_phone` — required.
     * - `description`, `address`, `lat`/`lng` (both or neither) — optional.
     * - `status` — `visible` or `invisible` (default `invisible`).
     * - `primary_user_id` — optional existing center user to assign as the primary owner.
     * - `tags`, `logo`, `cover`, `images` — same as the center portal update.
     */
    #[Response(status: 201, description: 'The created center as a `CenterResource`.')]
    #[Response(status: 403, description: 'Missing the `centers.create` permission.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function store(StoreCenterRequest $request): JsonResponse
    {
        $center = $this->service->create($request->user(), $request->validated());

        return CenterResource::make($center)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a center.
     */
    #[Response(status: 200, description: 'The center as a `CenterResource`, with `city`, `tags`, `logo`, `cover` and `images`.')]
    #[Response(status: 403, description: 'Missing the `centers.view` permission.')]
    #[Response(status: 404, description: 'No center with that id.')]
    public function show(Request $request, Center $center): CenterResource
    {
        Gate::forUser($request->user())->authorize('view', $center);

        return CenterResource::make($this->service->find($center));
    }

    /**
     * Update a center.
     *
     * Partial update; same fields as create except `primary_user_id`.
     */
    #[Response(status: 200, description: 'The updated center as a `CenterResource`.')]
    #[Response(status: 403, description: 'Missing the `centers.update` permission.')]
    #[Response(status: 404, description: 'No center with that id.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function update(UpdateCenterRequest $request, Center $center): CenterResource
    {
        return CenterResource::make($this->service->update($request->user(), $center, $request->validated()));
    }

    /**
     * Delete a center.
     *
     * Soft-deletes the center.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'Missing the `centers.delete` permission.')]
    #[Response(status: 404, description: 'No center with that id.')]
    public function destroy(Request $request, Center $center): \Illuminate\Http\Response
    {
        Gate::forUser($request->user())->authorize('delete', $center);

        $this->service->delete($center);

        return response()->noContent();
    }
}
