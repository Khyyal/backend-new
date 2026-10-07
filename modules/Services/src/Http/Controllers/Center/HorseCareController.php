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
use Modules\Services\Http\Requests\Center\HorseCareRequest;
use Modules\Services\Http\Resources\Center\HorseCareResource;
use Modules\Services\Models\HorseCare;
use Modules\Services\Services\CenterHorseCareService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Horse Care', description: 'Manage the horse care services of a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class HorseCareController extends Controller
{
    public function __construct(private readonly CenterHorseCareService $service) {}

    /**
     * List the center's horse care services.
     *
     * Paginated (15 per page, `?page=`), newest first. Each item is a
     * `HorseCareResource`.
     */
    #[Response(status: 200, description: 'Paginated `HorseCareResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $horseCares = HorseCare::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.media'])
            ->latest('id')
            ->paginate(15);

        return HorseCareResource::collection($horseCares);
    }

    /**
     * Create a horse care service.
     *
     * **Request body:**
     * - `name`                    required object; `ar` required string, `en` optional string.
     * - `description`             required object; `ar` required string, `en` optional string.
     * - `price_options`           required array (1-20) of `{ name, price }`.
     * - `cover`                   optional media id (uuid) or `null`.
     * - `images`                  optional array (max 10) of media ids, in display order.
     *
     * Media ids come from the Support media API (`POST /media`); temporary
     * uploads must belong to the authenticated user and not be expired.
     *
     * The service is created `active`.
     */
    #[Response(status: 201, description: 'The created service as a `HorseCareResource`: `id`, `service_id`, `center_id`, `slug`, `status`, `name` {ar,en}, `description` {ar,en}, `price_options` [{id,name,price}], `cover`, `images`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function store(HorseCareRequest $request, Center $center): JsonResponse
    {
        $horseCare = $this->service->create($center, $this->user($request), $request->validated());

        return HorseCareResource::make($horseCare)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a horse care service.
     */
    #[Response(status: 200, description: 'The service as a `HorseCareResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function show(Request $request, Center $center, int $horseCare): HorseCareResource
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return HorseCareResource::make($this->service->findForCenter($center, $horseCare));
    }

    /**
     * Update a horse care service.
     *
     * Replaces the definition: `name`, `description` and `price_options` are
     * all required and take the same shape as on create (the old price
     * options are replaced). `cover` and `images` are optional and untouched
     * when omitted: `cover` takes a media id (`null` removes it); `images` is
     * the full list of media ids in display order, so existing ids are kept,
     * new temporary ids are attached and omitted ones are removed.
     */
    #[Response(status: 200, description: 'The updated service as a `HorseCareResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function update(HorseCareRequest $request, Center $center, int $horseCare): HorseCareResource
    {
        $model = $this->service->findForCenter($center, $horseCare);

        return HorseCareResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    /**
     * Delete a horse care service.
     *
     * Soft-deletes the service together with its price options.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function destroy(Request $request, Center $center, int $horseCare): \Illuminate\Http\Response
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $this->service->delete($this->service->findForCenter($center, $horseCare));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        return $request->user('center_user');
    }
}
