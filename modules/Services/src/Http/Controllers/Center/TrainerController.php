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
use Modules\Services\Enums\ServiceType;
use Modules\Services\Http\Requests\Center\TrainerRequest;
use Modules\Services\Http\Resources\Center\TrainerOptionResource;
use Modules\Services\Http\Resources\Center\TrainerResource;
use Modules\Services\Models\Trainer;
use Modules\Services\Services\TrainerService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Trainers', 'The trainers of a center, their weekly schedules and hourly limits. Served on `centers/{center}/…` (center-scoped token; center users need `services.training.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class TrainerController extends Controller
{
    public function __construct(private readonly TrainerService $service) {}

    #[Endpoint('List trainers', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(TrainerResource::class, Trainer::class, status: 200, description: 'Paginated trainers.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Training]);

        return TrainerResource::collection($this->service->list($center));
    }

    #[Endpoint('List all trainers (id and name)', 'For pickers. Ordered by name, not paginated.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(TrainerOptionResource::class, Trainer::class, status: 200, description: 'All trainers.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function list(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Training]);

        return TrainerOptionResource::collection($this->service->simpleList($center));
    }

    #[Endpoint('Create a trainer')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(TrainerResource::class, Trainer::class, status: 201, description: 'The created trainer.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'Each end time must be after its start time.', 'errors' => ['hours' => ['Each end time must be after its start time.']]], 422, 'Validation failed.')]
    public function store(TrainerRequest $request, Center $center): JsonResponse
    {
        $trainer = $this->service->create($center, $request->validated());

        return TrainerResource::make($trainer)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a trainer')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('trainer', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseFromApiResource(TrainerResource::class, Trainer::class, status: 200, description: 'The trainer.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such trainer in this center.')]
    public function show(Request $request, Center $center, int $trainer): TrainerResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Training]);

        return TrainerResource::make($this->service->findForCenter($center, $trainer));
    }

    #[Endpoint('Update a trainer', 'Replaces the definition (old schedules are replaced). The hourly limits reset to null when omitted; `training_service_ids` is left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('trainer', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseFromApiResource(TrainerResource::class, Trainer::class, status: 200, description: 'The updated trainer.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such trainer in this center.')]
    #[ApiResponse(['message' => 'The days field is required.', 'errors' => ['days' => ['The days field is required.']]], 422, 'Validation failed.')]
    public function update(TrainerRequest $request, Center $center, int $trainer): TrainerResource
    {
        $model = $this->service->findForCenter($center, $trainer);

        return TrainerResource::make($this->service->update($model, $request->validated()));
    }

    #[Endpoint('Delete a trainer', 'Soft-deletes the trainer with its schedules and detaches it from its training services.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('trainer', 'integer', 'Trainer ID.', example: 6)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such trainer in this center.')]
    public function destroy(Request $request, Center $center, int $trainer): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Training]);

        $this->service->delete($this->service->findForCenter($center, $trainer));

        return response()->noContent();
    }
}
