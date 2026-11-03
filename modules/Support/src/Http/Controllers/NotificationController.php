<?php

namespace Modules\Support\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Support\Http\Resources\NotificationResource;

/**
 * Notification inbox shared by clients, center users and admins.
 * The guard of the route decides whose notifications are served.
 */
#[Group(name: 'Notifications', description: 'In-app notification inbox for the authenticated user.')]
class NotificationController extends Controller
{
    /**
     * List notifications.
     *
     * Newest first, 15 per page. `title` and `body` are returned in the
     * `Accept-Language` locale; `payload` keeps every locale plus deep-link ids.
     *
     * @return AnonymousResourceCollection<int, NotificationResource>
     */
    #[QueryParameter('read', description: 'Filter by read state: `true` for read only, `false` for unread only.', type: 'boolean')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = match ($request->query('read')) {
            'true', '1' => $user->readNotifications(),
            'false', '0' => $user->unreadNotifications(),
            default => $user->notifications(),
        };

        return NotificationResource::collection($notifications->paginate(15));
    }

    /**
     * Unread notifications count.
     */
    #[Response(status: 200, description: 'Returns `unread_count`.')]
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification as read.
     */
    #[Response(status: 200, description: 'The updated notification.')]
    #[Response(status: 404, description: 'The notification does not belong to the authenticated user.')]
    public function markAsRead(Request $request, string $notification): NotificationResource
    {
        $model = $request->user()->notifications()->findOrFail($notification);
        $model->markAsRead();

        return NotificationResource::make($model);
    }

    /**
     * Mark all notifications as read.
     */
    #[Response(status: 200, description: 'Returns how many notifications were marked as `updated`.')]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['updated' => $updated]);
    }
}
