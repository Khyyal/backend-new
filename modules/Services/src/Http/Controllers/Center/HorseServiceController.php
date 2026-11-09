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
use Modules\Services\Http\Requests\Center\HorseServiceRequest;
use Modules\Services\Http\Resources\Center\HorseServiceResource;
use Modules\Services\Models\HorseService;
use Modules\Services\Services\CenterHorseServiceService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Horse services', 'The horse services of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.horse_service.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class HorseServiceController extends Controller
{
    public function __construct(private readonly CenterHorseServiceService $service) {}

    #[Endpoint('List horse services', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(HorseServiceResource::class, HorseService::class, status: 200, description: 'Paginated horse services.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.horse_service.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::HorseService]);

        $horseServices = HorseService::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.media'])
            ->latest('id')
            ->paginate(15);

        return HorseServiceResource::collection($horseServices);
    }

    #[Endpoint('Create a horse service', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(HorseServiceResource::class, HorseService::class, status: 201, description: 'The created horse service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.horse_service.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The price options field is required.', 'errors' => ['price_options' => ['The price options field is required.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function store(HorseServiceRequest $request, Center $center): JsonResponse
    {
        $horseService = $this->service->create($center, $this->user($request), $request->validated());

        return HorseServiceResource::make($horseService)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a horse service')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('horseService', 'integer', 'ID of the horse service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(HorseServiceResource::class, HorseService::class, status: 200, description: 'The horse service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.horse_service.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such horse service in this center.')]
    public function show(Request $request, Center $center, int $horseService): HorseServiceResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::HorseService]);

        return HorseServiceResource::make($this->service->findForCenter($center, $horseService));
    }

    #[Endpoint('Update a horse service', 'Replaces the definition: send the same body as on create (old price options, schedules and day prices are replaced). `cover`, `images`, `settings`, `packages` and trainer lists are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('horseService', 'integer', 'ID of the horse service (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(HorseServiceResource::class, HorseService::class, status: 200, description: 'The updated horse service.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.horse_service.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such horse service in this center.')]
    #[ApiResponse(['message' => 'The price options field is required.', 'errors' => ['price_options' => ['The price options field is required.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(HorseServiceRequest $request, Center $center, int $horseService): HorseServiceResource
    {
        $model = $this->service->findForCenter($center, $horseService);

        return HorseServiceResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a horse service', 'Soft-deletes the service with its price options.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('horseService', 'integer', 'ID of the horse service (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.horse_service.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such horse service in this center.')]
    public function destroy(Request $request, Center $center, int $horseService): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::HorseService]);

        $this->service->delete($this->service->findForCenter($center, $horseService));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
