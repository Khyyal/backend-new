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
use Modules\Services\Http\Requests\Center\ResortRequest;
use Modules\Services\Http\Resources\Center\ResortResource;
use Modules\Services\Models\Resort;
use Modules\Services\Services\CenterResortService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Resort', description: 'Manage the resort services of a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ResortController extends Controller
{
    public function __construct(private readonly CenterResortService $service) {}

    /**
     * List the center's resort services.
     *
     * Paginated (15 per page, `?page=`), newest first. Each item is a
     * `ResortResource`.
     */
    #[Response(status: 200, description: 'Paginated `ResortResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $resorts = Resort::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.media', 'dayPrices'])
            ->latest('id')
            ->paginate(15);

        return ResortResource::collection($resorts);
    }

    /**
     * Create a resort service.
     *
     * **Request body:**
     * - `name`                    required object; `ar` required string, `en` optional string.
     * - `description`             required object; `ar` required string, `en` optional string.
     * - `days`                    required array (1-7) of `{ day, price }`; `day` is a distinct weekday number `0`-`6`, `price` a number >= 0. Partial weekday coverage is allowed.
     * - `price_options`           required array (1-20) of `{ name, price }`.
     * - `cover`                   optional media id (uuid) or `null`.
     * - `images`                  optional array (max 10) of media ids, in display order.
     *
     * Media ids come from the Support media API (`POST /media`); temporary
     * uploads must belong to the authenticated user and not be expired.
     *
     * The service is created `active`.
     */
    #[Response(status: 201, description: 'The created service as a `ResortResource`: `id`, `service_id`, `center_id`, `slug`, `status`, `name` {ar,en}, `description` {ar,en}, `days` [{day,price}], `price_options` [{id,name,price}], `cover`, `images`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function store(ResortRequest $request, Center $center): JsonResponse
    {
        $resort = $this->service->create($center, $this->user($request), $request->validated());

        return ResortResource::make($resort)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a resort service.
     */
    #[Response(status: 200, description: 'The service as a `ResortResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function show(Request $request, Center $center, int $resort): ResortResource
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        return ResortResource::make($this->service->findForCenter($center, $resort));
    }

    /**
     * Update a resort service.
     *
     * Replaces the definition: `name`, `description`, `days` and
     * `price_options` are all required and take the same shape as on create
     * (the old day prices and price options are replaced). `cover` and
     * `images` are optional and untouched when omitted: `cover` takes a media
     * id (`null` removes it); `images` is the full list of media ids in
     * display order, so existing ids are kept, new temporary ids are attached
     * and omitted ones are removed.
     */
    #[Response(status: 200, description: 'The updated service as a `ResortResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function update(ResortRequest $request, Center $center, int $resort): ResortResource
    {
        $model = $this->service->findForCenter($center, $resort);

        return ResortResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    /**
     * Delete a resort service.
     *
     * Soft-deletes the service together with its day prices and price options.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such service in this center.')]
    public function destroy(Request $request, Center $center, int $resort): \Illuminate\Http\Response
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $this->service->delete($this->service->findForCenter($center, $resort));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        return $request->user('center_user');
    }
}
