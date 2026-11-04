<?php

namespace Modules\Services\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Http\Requests\Center\MembershipRequest;
use Modules\Services\Http\Resources\Center\MembershipResource;
use Modules\Services\Models\Membership;
use Modules\Services\Services\CenterMembershipService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Memberships', description: 'Manage the memberships of a center: bundles of service packages sold for one price. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class MembershipController extends Controller
{
    public function __construct(private readonly CenterMembershipService $service) {}

    /**
     * List the center's memberships.
     *
     * Paginated (15 per page, `?page=`), newest first. Each item is a
     * `MembershipResource`.
     */
    #[Response(status: 200, description: 'Paginated `MembershipResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Membership]);

        $memberships = Membership::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.media', 'packages.servicePackage.service', 'packages.trainer', 'visitService'])
            ->latest('id')
            ->paginate(15);

        return MembershipResource::collection($memberships);
    }

    /**
     * Create a membership.
     *
     * **Request body:**
     * - `name`               required object; `ar` required string, `en` optional string.
     * - `description`        required object; `ar` required string, `en` optional string.
     * - `price`              required integer >= 0 (minor units), the price of the whole membership.
     * - `duration`           required integer 1-120, validity of the membership in months.
     * - `color`              required hex color `#RRGGBB`.
     * - `visit_service_id`   optional visit service of this center; the member gets free entries to it.
     * - `max_visits_per_day` required with `visit_service_id`, integer >= 1: free visit entries per day.
     * - `packages`           required array (1-20) of `{ service_package_id, trainer_id }`: service packages of this center's services. `trainer_id` is optional and only accepted on a training package, for a trainer of that training service.
     * - `cover`              optional media id (uuid) or `null`.
     * - `images`             optional array (max 10) of media ids, in display order.
     *
     * The service is created `active`.
     */
    #[Response(status: 201, description: 'The created membership as a `MembershipResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function store(MembershipRequest $request, Center $center): JsonResponse
    {
        $membership = $this->service->create($center, $this->user($request), $request->validated());

        return MembershipResource::make($membership)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a membership.
     */
    #[Response(status: 200, description: 'The membership as a `MembershipResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such membership in this center.')]
    public function show(Request $request, Center $center, int $membership): MembershipResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Membership]);

        return MembershipResource::make($this->service->findForCenter($center, $membership));
    }

    /**
     * Update a membership.
     *
     * Replaces the definition with the same body as on create: `packages` is
     * the full list of included packages. Memberships already sold keep the
     * packages they were bought with. `cover` and `images` are optional and
     * untouched when omitted.
     */
    #[Response(status: 200, description: 'The updated membership as a `MembershipResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such membership in this center.')]
    #[Response(status: 422, description: 'Validation failed, or a media id is invalid, expired or not owned by the user.')]
    public function update(MembershipRequest $request, Center $center, int $membership): MembershipResource
    {
        $model = $this->service->findForCenter($center, $membership);

        return MembershipResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    /**
     * Delete a membership.
     *
     * Soft-deletes the membership with its service and price option. Memberships
     * already sold are kept.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 404, description: 'No such membership in this center.')]
    public function destroy(Request $request, Center $center, int $membership): \Illuminate\Http\Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Membership]);

        $this->service->delete($this->service->findForCenter($center, $membership));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
