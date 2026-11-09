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
use Modules\Services\Http\Requests\Center\RecreationRidingRequest;
use Modules\Services\Http\Resources\Center\RecreationRidingResource;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Services\CenterRecreationRidingService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Recreation riding', 'The recreation riding services of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.recreation_riding.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class RecreationRidingController extends Controller
{
    public function __construct(private readonly CenterRecreationRidingService $service) {}

    #[Endpoint('List recreation riding services', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(RecreationRidingResource::class, RecreationalRiding::class, status: 200, description: 'Paginated recreation riding services.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.recreation_riding.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::RecreationRiding]);

        $ridings = RecreationalRiding::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions.package', 'service.settings', 'service.media', 'schedules'])
            ->latest('id')
            ->paginate(15);

        return RecreationRidingResource::collection($ridings);
    }

    #[Endpoint('Create a recreation riding service', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(RecreationRidingResource::class, RecreationalRiding::class, status: 201, description: 'The created recreation riding service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.recreation_riding.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'Each end time must be after its start time.', 'errors' => ['hours' => ['Each end time must be after its start time.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function store(RecreationRidingRequest $request, Center $center): JsonResponse
    {
        $riding = $this->service->create($center, $this->user($request), $request->validated());

        return RecreationRidingResource::make($riding)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a recreation riding service')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('recreationRiding', 'integer', 'ID of the recreation riding service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(RecreationRidingResource::class, RecreationalRiding::class, status: 200, description: 'The recreation riding service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.recreation_riding.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such recreation riding service in this center.')]
    public function show(Request $request, Center $center, int $recreationRiding): RecreationRidingResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::RecreationRiding]);

        return RecreationRidingResource::make($this->service->findForCenter($center, $recreationRiding));
    }

    #[Endpoint('Update a recreation riding service', 'Replaces the definition: send the same body as on create (old price options, schedules and day prices are replaced). `cover`, `images`, `settings`, `packages` and trainer lists are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('recreationRiding', 'integer', 'ID of the recreation riding service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(RecreationRidingResource::class, RecreationalRiding::class, status: 200, description: 'The updated recreation riding service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.recreation_riding.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such recreation riding service in this center.')]
    #[ApiResponse(['message' => 'Each end time must be after its start time.', 'errors' => ['hours' => ['Each end time must be after its start time.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(RecreationRidingRequest $request, Center $center, int $recreationRiding): RecreationRidingResource
    {
        $riding = $this->service->findForCenter($center, $recreationRiding);

        return RecreationRidingResource::make(
            $this->service->update($center, $riding, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a recreation riding service', 'Soft-deletes the service with its price options and schedules.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('recreationRiding', 'integer', 'ID of the recreation riding service (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.recreation_riding.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such recreation riding service in this center.')]
    public function destroy(Request $request, Center $center, int $recreationRiding): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::RecreationRiding]);

        $this->service->delete($this->service->findForCenter($center, $recreationRiding));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
