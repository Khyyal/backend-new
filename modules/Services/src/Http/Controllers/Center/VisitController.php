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
use Modules\Services\Http\Requests\Center\VisitRequest;
use Modules\Services\Http\Resources\Center\VisitResource;
use Modules\Services\Models\Visit;
use Modules\Services\Services\CenterVisitService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Visits', 'The visit services of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.visit.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class VisitController extends Controller
{
    public function __construct(private readonly CenterVisitService $service) {}

    #[Endpoint('List visit services', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(VisitResource::class, Visit::class, status: 200, description: 'Paginated visit services.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.visit.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Visit]);

        $visits = Visit::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.settings', 'service.media', 'schedules'])
            ->latest('id')
            ->paginate(15);

        return VisitResource::collection($visits);
    }

    #[Endpoint('Create a visit service', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(VisitResource::class, Visit::class, status: 201, description: 'The created visit service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.visit.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The hours field is required when enter type is specific time.', 'errors' => ['hours' => ['The hours field is required when enter type is specific time.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function store(VisitRequest $request, Center $center): JsonResponse
    {
        $visit = $this->service->create($center, $this->user($request), $request->validated());

        return VisitResource::make($visit)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a visit service')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('visit', 'integer', 'ID of the visit service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(VisitResource::class, Visit::class, status: 200, description: 'The visit service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.visit.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such visit service in this center.')]
    public function show(Request $request, Center $center, int $visit): VisitResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Visit]);

        return VisitResource::make($this->service->findForCenter($center, $visit));
    }

    #[Endpoint('Update a visit service', 'Replaces the definition: send the same body as on create (old price options, schedules and day prices are replaced). `cover`, `images`, `settings`, `packages` and trainer lists are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('visit', 'integer', 'ID of the visit service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(VisitResource::class, Visit::class, status: 200, description: 'The updated visit service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.visit.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such visit service in this center.')]
    #[ApiResponse(['message' => 'The hours field is required when enter type is specific time.', 'errors' => ['hours' => ['The hours field is required when enter type is specific time.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(VisitRequest $request, Center $center, int $visit): VisitResource
    {
        $model = $this->service->findForCenter($center, $visit);

        return VisitResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a visit service', 'Soft-deletes the service with its price option and schedules.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('visit', 'integer', 'ID of the visit service (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.visit.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such visit service in this center.')]
    public function destroy(Request $request, Center $center, int $visit): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Visit]);

        $this->service->delete($this->service->findForCenter($center, $visit));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
