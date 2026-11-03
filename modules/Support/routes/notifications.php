<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\NotificationController;

/*
 * Center user inbox routes live in Centers/routes/center.php so they register before `/centers/{center}`.
 */
$notificationRoutes = function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
    Route::post('/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
};

Route::middleware(['set.locale.from.accept.language', 'auth:client'])
    ->prefix('clients/notifications')
    ->name('client.notifications.')
    ->group($notificationRoutes);

Route::middleware(['set.locale.from.accept.language', 'auth:system_user'])
    ->prefix('admin/notifications')
    ->name('admin.notifications.')
    ->group($notificationRoutes);
