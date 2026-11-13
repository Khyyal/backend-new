<?php

namespace Modules\Centers\Http\Controllers\Center;

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
use Modules\Centers\Http\Requests\Center\HorseRequest;
use Modules\Centers\Http\Resources\Center\HorseResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\Horse;
use Modules\Centers\Services\CenterHorseService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Horses', 'The horses kept by a center, owned by the center or by a client. Served on `centers/{center}/…` (center-scoped token; center users need `horses.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_horses`).')]
class HorseController extends Controller
{
    public function __construct(private readonly CenterHorseService $service) {}

    #[Endpoint('List horses', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('owner', 'string', 'Only horses owned by the `center` or by a `client`.', required: false, enum: ['center', 'client'], example: 'client')]
    #[QueryParam('client_id', 'integer', 'Only horses of this client.', required: false, example: 77)]
    #[QueryParam('q', 'string', 'Search in the horse name.', required: false, example: 'Najm')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(HorseResource::class, Horse::class, status: 200, description: 'Paginated horses.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `horses.manage` for this center (system users: `centers.manage_horses`).')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageHorses', $center);

        $filters = $request->validate([
            'owner' => ['nullable', 'in:center,client'],
            'client_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($filters['client_id'])) {
            $filters['client_id'] = (int) $filters['client_id'];
        }

        return HorseResource::collection(
            $this->service->queryForCenter($center, $filters)->latest('id')->paginate(15)
        );
    }

    #[Endpoint('Create a horse', 'With `client_id` the client owns the horse and is added to the center\'s clients; without it the center owns it.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(HorseResource::class, Horse::class, status: 201, description: 'The created horse.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `horses.manage` for this center (system users: `centers.manage_horses`).')]
    #[ApiResponse(['message' => 'The room field is required.', 'errors' => ['room' => ['The room field is required.']]], 422, 'Validation failed, or the image UUID is invalid, expired or not yours.')]
    public function store(HorseRequest $request, Center $center): JsonResponse
    {
        $horse = $this->service->create($center, $request->user(), $request->validated());

        return HorseResource::make($horse)->response()->setStatusCode(201);
    }

    #[Endpoint('Show a horse')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('horse', 'integer', 'Horse ID.', example: 5)]
    #[ResponseFromApiResource(HorseResource::class, Horse::class, status: 200, description: 'The horse.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `horses.manage` for this center (system users: `centers.manage_horses`).')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such horse in this center.')]
    public function show(Request $request, Center $center, int $horse): HorseResource
    {
        Gate::forUser($request->user())->authorize('manageHorses', $center);

        return HorseResource::make($this->service->findForCenter($center, $horse));
    }

    #[Endpoint('Update a horse', 'Send the same body as on create: an omitted or null `client_id` makes the center the owner. `image` is kept when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('horse', 'integer', 'Horse ID.', example: 5)]
    #[ResponseFromApiResource(HorseResource::class, Horse::class, status: 200, description: 'The updated horse.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `horses.manage` for this center (system users: `centers.manage_horses`).')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such horse in this center.')]
    #[ApiResponse(['message' => 'The room field is required.', 'errors' => ['room' => ['The room field is required.']]], 422, 'Validation failed, or the image UUID is invalid, expired or not yours.')]
    public function update(HorseRequest $request, Center $center, int $horse): HorseResource
    {
        $model = $this->service->findForCenter($center, $horse);

        return HorseResource::make($this->service->update($center, $model, $request->user(), $request->validated()));
    }

    #[Endpoint('Delete a horse', 'Soft-deletes the horse.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('horse', 'integer', 'Horse ID.', example: 5)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `horses.manage` for this center (system users: `centers.manage_horses`).')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such horse in this center.')]
    public function destroy(Request $request, Center $center, int $horse): Response
    {
        Gate::forUser($request->user())->authorize('manageHorses', $center);

        $this->service->delete($this->service->findForCenter($center, $horse));

        return response()->noContent();
    }
}
