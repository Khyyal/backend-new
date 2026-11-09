<?php

namespace Modules\Services\Http\Controllers\Center;

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
use Modules\Services\Http\Requests\Center\ServiceBlockRequest;
use Modules\Services\Http\Resources\Center\ServiceBlockResource;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Services\ServiceBlockService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Service blocks', 'Dates when a center\'s services (all of them, or one) cannot be booked. Served on `centers/{center}/…` (center-scoped token; center users need the `services.{type}.manage` permission of the blocked service type) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class ServiceBlockController extends Controller
{
    public function __construct(private readonly ServiceBlockService $service) {}

    #[Endpoint('List blocked dates', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('service_id', 'integer', 'Only blocks of this service.', required: false, example: 31)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ServiceBlockResource::class, ServiceBlock::class, status: 200, description: 'Paginated blocks.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the service permission for the blocked service (or for every type when blocking all services). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        $blocks = $this->service->list($center, $request->integer('service_id') ?: null);

        return ServiceBlockResource::collection($blocks);
    }

    #[Endpoint('Create a blocked date')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(ServiceBlockResource::class, ServiceBlock::class, status: 201, description: 'The created block.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the service permission for the blocked service (or for every type when blocking all services). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The hours field is required when time scope is specific_time.', 'errors' => ['hours' => ['The hours field is required when time scope is specific_time.']]], 422, 'Validation failed.')]
    public function store(ServiceBlockRequest $request, Center $center): JsonResponse
    {
        $this->authorizeBlockTarget($request, $center, $request->validated('service_id'));

        $block = $this->service->create($center, $request->validated());

        return ServiceBlockResource::make($block)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a blocked date')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('serviceBlock', 'integer', 'Block ID.', example: 4)]
    #[ResponseFromApiResource(ServiceBlockResource::class, ServiceBlock::class, status: 200, description: 'The block.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the service permission for the blocked service (or for every type when blocking all services). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such block in this center.')]
    public function show(Request $request, Center $center, int $serviceBlock): ServiceBlockResource
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        return ServiceBlockResource::make($this->service->findForCenter($center, $serviceBlock));
    }

    #[Endpoint('Update a blocked date', 'Replaces the definition; same body as create.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('serviceBlock', 'integer', 'Block ID.', example: 4)]
    #[ResponseFromApiResource(ServiceBlockResource::class, ServiceBlock::class, status: 200, description: 'The updated block.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the service permission for the blocked service (or for every type when blocking all services). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such block in this center.')]
    #[ApiResponse(['message' => 'The reason field is required.', 'errors' => ['reason' => ['The reason field is required.']]], 422, 'Validation failed.')]
    public function update(ServiceBlockRequest $request, Center $center, int $serviceBlock): ServiceBlockResource
    {
        $block = $this->service->findForCenter($center, $serviceBlock);
        $this->authorizeBlockTarget($request, $center, $block->service_id);
        $this->authorizeBlockTarget($request, $center, $request->validated('service_id'));

        return ServiceBlockResource::make($this->service->update($block, $request->validated()));
    }

    #[Endpoint('Delete a blocked date')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('serviceBlock', 'integer', 'Block ID.', example: 4)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the service permission for the blocked service (or for every type when blocking all services). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such block in this center.')]
    public function destroy(Request $request, Center $center, int $serviceBlock): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        $block = $this->service->findForCenter($center, $serviceBlock);
        $this->authorizeBlockTarget($request, $center, $block->service_id);

        $this->service->delete($block);

        return response()->noContent();
    }

    /**
     * A block on one service needs that service type's permission; a
     * center-wide block (no service) needs every service type's permission.
     */
    private function authorizeBlockTarget(Request $request, Center $center, int|string|null $serviceId): void
    {
        $gate = Gate::forUser($request->user());

        if ($serviceId === null) {
            $gate->authorize('manageAllServices', $center);

            return;
        }

        $service = $center->services()->findOrFail($serviceId);

        $gate->authorize('manageServices', [$center, $service->type]);
    }
}
