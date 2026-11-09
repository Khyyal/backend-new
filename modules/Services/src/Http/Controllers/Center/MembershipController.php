<?php

namespace Modules\Services\Http\Controllers\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Http\Requests\Center\MembershipRequest;
use Modules\Services\Http\Resources\Center\MembershipResource;
use Modules\Services\Models\Membership;
use Modules\Services\Services\CenterMembershipService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Memberships', 'The memberships of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.membership.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class MembershipController extends Controller
{
    public function __construct(private readonly CenterMembershipService $service) {}

    #[Endpoint('List memberships', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(MembershipResource::class, Membership::class, status: 200, description: 'Paginated memberships.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.membership.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
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

    #[Endpoint('Create a membership', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(MembershipResource::class, Membership::class, status: 201, description: 'The created membership.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.membership.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The max visits per day field is required when visit service id is present.', 'errors' => ['max_visits_per_day' => ['The max visits per day field is required when visit service id is present.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function store(MembershipRequest $request, Center $center): JsonResponse
    {
        $membership = $this->service->create($center, $this->user($request), $request->validated());

        return MembershipResource::make($membership)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a membership')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('membership', 'integer', 'ID of the membership (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(MembershipResource::class, Membership::class, status: 200, description: 'The membership.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.membership.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such membership in this center.')]
    public function show(Request $request, Center $center, int $membership): MembershipResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Membership]);

        return MembershipResource::make($this->service->findForCenter($center, $membership));
    }

    #[Endpoint('Update a membership', 'Replaces the definition: send the same body as on create (old price options, schedules and day prices are replaced). `cover`, `images`, `settings`, `packages` and trainer lists are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('membership', 'integer', 'ID of the membership (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(MembershipResource::class, Membership::class, status: 200, description: 'The updated membership.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.membership.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such membership in this center.')]
    #[ApiResponse(['message' => 'The max visits per day field is required when visit service id is present.', 'errors' => ['max_visits_per_day' => ['The max visits per day field is required when visit service id is present.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(MembershipRequest $request, Center $center, int $membership): MembershipResource
    {
        $model = $this->service->findForCenter($center, $membership);

        return MembershipResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a membership', 'Soft-deletes the membership with its service and price option. Memberships already sold are kept.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('membership', 'integer', 'ID of the membership (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.membership.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such membership in this center.')]
    public function destroy(Request $request, Center $center, int $membership): Response
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
