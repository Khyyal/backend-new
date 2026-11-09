<?php

namespace Modules\Analytics\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Analytics\Http\Requests\Client\EngagementEventsRequest;
use Modules\Analytics\Services\CenterEngagementRecorder;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Client / Engagement', 'Anonymous engagement tracking for centers. Guests may call it; no token needed.')]
class EngagementController extends Controller
{
    public function __construct(private readonly CenterEngagementRecorder $recorder) {}

    #[Unauthenticated]
    #[Endpoint('Report center engagement events', 'Aggregated per day and center; nothing identifying is stored. Events for unknown centers, or services that are not the center\'s, are ignored. Limited to 60 requests per minute.')]
    #[ApiResponse(['data' => ['accepted' => 3]], 202, 'Events counted.')]
    #[ResponseField('data.accepted', 'integer', 'Events counted.', example: 3)]
    #[ApiResponse(['message' => 'The events.0.service_id field is required when events.0.action is service_click.', 'errors' => ['events.0.service_id' => ['The events.0.service_id field is required when events.0.action is service_click.']]], 422, 'Validation failed.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function store(EngagementEventsRequest $request): JsonResponse
    {
        $accepted = $this->recorder->record($request->validated('events'));

        return response()->json(['data' => ['accepted' => $accepted]], ResponseAlias::HTTP_ACCEPTED);
    }
}
