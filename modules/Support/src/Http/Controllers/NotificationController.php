<?php

namespace Modules\Support\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Resources\NotificationResource;
use Modules\Support\Models\Notification;

/**
 * Notification inbox shared by clients, center users and admins.
 * The guard of the route decides whose notifications are served.
 */
#[Group('Notifications', 'In-app notification inbox of the authenticated actor. The route prefix decides whose inbox is served (`clients/notifications`, `centers/notifications`, `admin/notifications`).')]
class NotificationController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, NotificationResource>
     */
    #[Endpoint('List notifications', 'Newest first, 15 per page. `title` and `body` follow `Accept-Language`; `payload` keeps every locale plus deep-link IDs.')]
    #[QueryParam('read', 'boolean', '`true` for read only, `false` for unread only; omit for all.', required: false, example: false)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(NotificationResource::class, Notification::class, status: 200, description: 'Paginated notifications.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
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

    #[Endpoint('Count unread notifications', 'For the inbox badge.')]
    #[ApiResponse(['unread_count' => 3], 200, 'Unread count.')]
    #[ResponseField('unread_count', 'integer', 'Unread notifications.', example: 3)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    #[Endpoint('Mark a notification as read', 'Idempotent.')]
    #[UrlParam('notification', 'string', 'Notification UUID.', example: '0f8b7f2e-6a3c-4d51-9a2e-4b7c1d9e3f60')]
    #[ResponseFromApiResource(NotificationResource::class, Notification::class, status: 200, description: 'The notification, now read.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Not a notification of the authenticated actor.')]
    public function markAsRead(Request $request, string $notification): NotificationResource
    {
        $model = $request->user()->notifications()->findOrFail($notification);
        $model->markAsRead();

        return NotificationResource::make($model);
    }

    #[Endpoint('Mark all notifications as read')]
    #[ApiResponse(['updated' => 3], 200, 'How many were marked.')]
    #[ResponseField('updated', 'integer', 'Notifications that changed from unread to read.', example: 3)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['updated' => $updated]);
    }
}
