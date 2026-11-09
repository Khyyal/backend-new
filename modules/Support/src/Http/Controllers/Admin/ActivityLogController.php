<?php

namespace Modules\Support\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Requests\Admin\ListActivityLogsRequest;
use Modules\Support\Http\Resources\Admin\ActivityLogResource;
use Spatie\Activitylog\Models\Activity;

#[Group('Admin / Activity logs', 'Read-only audit trail of services, reservations, client packages and memberships, system users and roles, and subscriptions. Requires `activity_logs.view_any`.')]
class ActivityLogController extends Controller
{
    #[Endpoint('List activity logs', 'Newest first, paginated (25 per page by default). Filters are query parameters.')]
    #[ResponseFromApiResource(ActivityLogResource::class, Activity::class, status: 200, description: 'Paginated log entries.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `activity_logs.view_any`.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filter.')]
    public function index(ListActivityLogsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $logs = Activity::query()
            ->with('causer')
            ->whereIn('log_name', ListActivityLogsRequest::LOG_NAMES)
            ->when($filters['log_name'] ?? null, fn ($query, string $name) => $query->where('log_name', $name))
            ->when($filters['subject_id'] ?? null, fn ($query, int $id) => $query->where('subject_id', $id))
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['causer_id'] ?? null, fn ($query, int $id) => $query->where('causer_id', $id))
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }

    #[Endpoint('Show an activity log entry')]
    #[UrlParam('activity', 'integer', 'Log entry ID.', example: 5012)]
    #[ResponseFromApiResource(ActivityLogResource::class, Activity::class, status: 200, description: 'The log entry.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `activity_logs.view_any`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Unknown log entry.')]
    public function show(Request $request, int $activity): ActivityLogResource
    {
        Gate::forUser($request->user())->authorize('viewAny', Activity::class);

        return ActivityLogResource::make(
            Activity::query()->with('causer')->whereIn('log_name', ListActivityLogsRequest::LOG_NAMES)->findOrFail($activity)
        );
    }
}
