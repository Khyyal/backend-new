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
use Modules\Services\Http\Requests\Center\ResortRequest;
use Modules\Services\Http\Resources\Center\ResortResource;
use Modules\Services\Models\Resort;
use Modules\Services\Services\CenterResortService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Resorts', 'The resorts of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.resort.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class ResortController extends Controller
{
    public function __construct(private readonly CenterResortService $service) {}

    #[Endpoint('List resorts', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ResortResource::class, Resort::class, status: 200, description: 'Paginated resorts.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.resort.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Resort]);

        $resorts = Resort::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.settings', 'service.media'])
            ->latest('id')
            ->paginate(15);

        return ResortResource::collection($resorts);
    }

    #[Endpoint('Create a resort', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(ResortResource::class, Resort::class, status: 201, description: 'The created resort.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.resort.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The days.0.day field has a duplicate value.', 'errors' => ['days.0.day' => ['The days.0.day field has a duplicate value.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function store(ResortRequest $request, Center $center): JsonResponse
    {
        $resort = $this->service->create($center, $this->user($request), $request->validated());

        return ResortResource::make($resort)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a resort')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('resort', 'integer', 'ID of the resort (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(ResortResource::class, Resort::class, status: 200, description: 'The resort.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.resort.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such resort in this center.')]
    public function show(Request $request, Center $center, int $resort): ResortResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Resort]);

        return ResortResource::make($this->service->findForCenter($center, $resort));
    }

    #[Endpoint('Update a resort', 'Replaces the definition: send the same body as on create (old price options, schedules and day prices are replaced). `cover`, `images`, `settings`, `packages` and trainer lists are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('resort', 'integer', 'ID of the resort (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(ResortResource::class, Resort::class, status: 200, description: 'The updated resort.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.resort.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such resort in this center.')]
    #[ApiResponse(['message' => 'The days.0.day field has a duplicate value.', 'errors' => ['days.0.day' => ['The days.0.day field has a duplicate value.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(ResortRequest $request, Center $center, int $resort): ResortResource
    {
        $model = $this->service->findForCenter($center, $resort);

        return ResortResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a resort', 'Soft-deletes the service with its day prices and price options.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('resort', 'integer', 'ID of the resort (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.resort.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such resort in this center.')]
    public function destroy(Request $request, Center $center, int $resort): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Resort]);

        $this->service->delete($this->service->findForCenter($center, $resort));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
