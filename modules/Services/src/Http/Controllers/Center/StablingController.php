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
use Modules\Services\Http\Requests\Center\StablingRequest;
use Modules\Services\Http\Resources\Center\StablingResource;
use Modules\Services\Models\Stabling;
use Modules\Services\Services\CenterStablingService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Stabling', 'The stabling services of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.stabling.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class StablingController extends Controller
{
    public function __construct(private readonly CenterStablingService $service) {}

    #[Endpoint('List stabling services', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(StablingResource::class, Stabling::class, status: 200, description: 'Paginated stabling services.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.stabling.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Stabling]);

        $stablings = Stabling::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.media'])
            ->latest('id')
            ->paginate(15);

        return StablingResource::collection($stablings);
    }

    #[Endpoint('Create a stabling service', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(StablingResource::class, Stabling::class, status: 201, description: 'The created stabling service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.stabling.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The monthly prices field is required.', 'errors' => ['monthly_prices' => ['The monthly prices field is required.']]], 422, 'Validation failed, a price option `id` does not belong to this service, or a media UUID is invalid, expired or not yours.')]
    public function store(StablingRequest $request, Center $center): JsonResponse
    {
        $stabling = $this->service->create($center, $this->user($request), $request->validated());

        return StablingResource::make($stabling)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a stabling service')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('stabling', 'integer', 'ID of the stabling service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(StablingResource::class, Stabling::class, status: 200, description: 'The stabling service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.stabling.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such stabling service in this center.')]
    public function show(Request $request, Center $center, int $stabling): StablingResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Stabling]);

        return StablingResource::make($this->service->findForCenter($center, $stabling));
    }

    #[Endpoint('Update a stabling service', 'Replaces the definition: send the same body as on create. Price options sent with their `id` are updated in place; options without `id` are created; existing options missing from the body (including an omitted `daily_price` or `extra_options`) are deleted. `cover` and `images` are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('stabling', 'integer', 'ID of the stabling service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(StablingResource::class, Stabling::class, status: 200, description: 'The updated stabling service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.stabling.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such stabling service in this center.')]
    #[ApiResponse(['message' => 'The monthly prices field is required.', 'errors' => ['monthly_prices' => ['The monthly prices field is required.']]], 422, 'Validation failed, a price option `id` does not belong to this service, or a media UUID is invalid, expired or not yours.')]
    public function update(StablingRequest $request, Center $center, int $stabling): StablingResource
    {
        $model = $this->service->findForCenter($center, $stabling);

        return StablingResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a stabling service', 'Soft-deletes the service with its price options.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('stabling', 'integer', 'ID of the stabling service (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.stabling.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such stabling service in this center.')]
    public function destroy(Request $request, Center $center, int $stabling): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Stabling]);

        $this->service->delete($this->service->findForCenter($center, $stabling));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
