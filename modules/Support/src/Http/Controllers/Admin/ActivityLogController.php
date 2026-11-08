<?php

namespace Modules\Support\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Support\Http\Requests\Admin\ListActivityLogsRequest;
use Modules\Support\Http\Resources\Admin\ActivityLogResource;
use Spatie\Activitylog\Models\Activity;

#[Group(name: 'Admin / Activity logs', description: 'Read-only audit trail of services, reservations, client packages and client memberships. Requires a system user Bearer token with the `activity_logs.view_any` permission.')]
class ActivityLogController extends Controller
{
    /**
     * List activity logs.
     *
     * Newest first, paginated (25 per page, `per_page` up to 100).
     */
    #[Response(status: 200, description: 'Paginated `ActivityLogResource` collection.')]
    #[Response(status: 403, description: 'Missing the `activity_logs.view_any` permission.')]
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

    /**
     * Show an activity log.
     */
    #[Response(status: 200, description: '`ActivityLogResource`.')]
    #[Response(status: 403, description: 'Missing the `activity_logs.view_any` permission.')]
    #[Response(status: 404, description: 'Unknown activity log.')]
    public function show(Request $request, int $activity): ActivityLogResource
    {
        Gate::forUser($request->user())->authorize('viewAny', Activity::class);

        return ActivityLogResource::make(
            Activity::query()->with('causer')->whereIn('log_name', ListActivityLogsRequest::LOG_NAMES)->findOrFail($activity)
        );
    }
}
