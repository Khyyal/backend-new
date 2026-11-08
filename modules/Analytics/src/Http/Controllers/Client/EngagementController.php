<?php

namespace Modules\Analytics\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Analytics\Http\Requests\Client\EngagementEventsRequest;
use Modules\Analytics\Services\CenterEngagementRecorder;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Client / Engagement', description: 'Anonymous engagement tracking for centers. Guests may call it; no Bearer token is required.')]
class EngagementController extends Controller
{
    public function __construct(private readonly CenterEngagementRecorder $recorder) {}

    /**
     * Report center engagement events.
     *
     * Batched client actions (max 50 per request): `center_click` (opened a
     * center), `service_click` (opened one of its services, `service_id`
     * required), `map_click` (opened the center's location) and
     * `whatsapp_click` (opened the center's WhatsApp). Aggregated per day and
     * center; nothing identifying is stored. Events for unknown centers, or
     * services that are not the center's, are ignored. Throttled to 60
     * requests per minute.
     */
    #[ApiResponse(status: 202, description: '`{data: {accepted: int}}`, the number of events counted.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    #[ApiResponse(status: 429, description: 'Too many requests.')]
    public function store(EngagementEventsRequest $request): JsonResponse
    {
        $accepted = $this->recorder->record($request->validated('events'));

        return response()->json(['data' => ['accepted' => $accepted]], ResponseAlias::HTTP_ACCEPTED);
    }
}
