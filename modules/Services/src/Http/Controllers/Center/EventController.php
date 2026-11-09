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
use Modules\Services\Http\Requests\Center\EventRequest;
use Modules\Services\Http\Resources\Center\EventResource;
use Modules\Services\Models\Event;
use Modules\Services\Services\CenterEventService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Events', 'The events of a center. Served on `centers/{center}/…` (center-scoped token; center users need `services.event.manage`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class EventController extends Controller
{
    public function __construct(private readonly CenterEventService $service) {}

    #[Endpoint('List events', 'Newest first, 15 per page.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(EventResource::class, Event::class, status: 200, description: 'Paginated events.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.event.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Event]);

        $events = Event::query()
            ->whereHas('service', fn ($query) => $query->where('center_id', $center->getKey()))
            ->with(['service.priceOptions', 'service.settings', 'service.media'])
            ->latest('id')
            ->paginate(15);

        return EventResource::collection($events);
    }

    #[Endpoint('Create a event', 'Created `active`. Media UUIDs must be your own unexpired temporary uploads.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(EventResource::class, Event::class, status: 201, description: 'The created event.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.event.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The end date field must be a date after or equal to start date.', 'errors' => ['end_date' => ['The end date field must be a date after or equal to start date.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function store(EventRequest $request, Center $center): JsonResponse
    {
        $event = $this->service->create($center, $this->user($request), $request->validated());

        return EventResource::make($event)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a event')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('event', 'integer', 'ID of the event (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(EventResource::class, Event::class, status: 200, description: 'The event.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.event.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such event in this center.')]
    public function show(Request $request, Center $center, int $event): EventResource
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Event]);

        return EventResource::make($this->service->findForCenter($center, $event));
    }

    #[Endpoint('Update a event', 'Replaces the definition: send the same body as on create (old price options, schedules and day prices are replaced). `cover`, `images`, `settings`, `packages` and trainer lists are left untouched when omitted.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('event', 'integer', 'ID of the event (the `id` field, not `service_id`).', example: 12)]
    #[ResponseFromApiResource(EventResource::class, Event::class, status: 200, description: 'The updated event.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.event.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such event in this center.')]
    #[ApiResponse(['message' => 'The end date field must be a date after or equal to start date.', 'errors' => ['end_date' => ['The end date field must be a date after or equal to start date.']]], 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(EventRequest $request, Center $center, int $event): EventResource
    {
        $model = $this->service->findForCenter($center, $event);

        return EventResource::make(
            $this->service->update($center, $model, $this->user($request), $request->validated())
        );
    }

    #[Endpoint('Delete a event', 'Soft-deletes the service with its price options.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('event', 'integer', 'ID of the event (the `id` field, not `service_id`).', example: 12)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `services.event.manage` for this center (system users: `centers.manage_services`). Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such event in this center.')]
    public function destroy(Request $request, Center $center, int $event): Response
    {
        Gate::forUser($request->user())->authorize('manageServices', [$center, ServiceType::Event]);

        $this->service->delete($this->service->findForCenter($center, $event));

        return response()->noContent();
    }

    private function user(Request $request): Model
    {
        return $request->user();
    }
}
