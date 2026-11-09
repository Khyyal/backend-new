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
use Modules\Services\Http\Requests\Center\TrainingServiceRequest;
use Modules\Services\Http\Resources\Center\TrainingServiceResource;
use Modules\Services\Models\TrainingService;
use Modules\Services\Services\CenterTrainingServiceService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Training services', 'The training services of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.training.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class TrainingServiceController extends Controller
{
    public function __construct(private readonly CenterTrainingServiceService $service) {}

    #[Endpoint('List training services', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(TrainingServiceResource::class, TrainingService::class, status: 200, description: 'Paginated training services.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Training]);

        $trainingServices = TrainingService::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions.package', 'service.settings', 'service.media', 'trainers'])
            ->latest('id')
            ->paginate(15);

        return TrainingServiceResource::collection($trainingServices);
    }

    #[Endpoint('Create a training service', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(TrainingServiceResource::class, TrainingService::class, status: 201, description: 'The created training service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The selected trainer_ids.0 is invalid.', 'errors' => ['trainer_ids.0' => ['The selected trainer_ids.0 is invalid.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function store(TrainingServiceRequest $request, Center $center): JsonResponse
    {
        $trainingService = $this->service->create($center, $this->user($request), $request->validated());

        return TrainingServiceResource::make($trainingService)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a training service')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('trainingService', 'integer', 'ID of the training service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(TrainingServiceResource::class, TrainingService::class, status: 200, description: 'The training service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such training service in this center.')]
    public function show(Request $request, Center $center, int $trainingService): TrainingServiceResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Training]);

        return TrainingServiceResource::make($this->service->findForCenter($center, $trainingService));
    }

    #[Endpoint('Update a training service', 'Replaces the definition: send the same body as on create (old price options, schedules and day prices are replaced). `cover`, `images`, `settings`, `packages` and trainer lists are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('trainingService', 'integer', 'ID of the training service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(TrainingServiceResource::class, TrainingService::class, status: 200, description: 'The updated training service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such training service in this center.')]
    #[ApiResponse(['message' => 'The selected trainer_ids.0 is invalid.', 'errors' => ['trainer_ids.0' => ['The selected trainer_ids.0 is invalid.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(TrainingServiceRequest $request, Center $center, int $trainingService): TrainingServiceResource
    {
        $model = $this->service->findForCenter($center, $trainingService);

        return TrainingServiceResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a training service', 'Soft-deletes the service with its price options and detaches its trainers (the trainers are kept).')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('trainingService', 'integer', 'ID of the training service (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.training.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such training service in this center.')]
    public function destroy(Request $request, Center $center, int $trainingService): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Training]);

        $this->service->delete($this->service->findForCenter($center, $trainingService));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
