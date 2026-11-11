<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Center\AccountController;
use Modules\Centers\Http\Controllers\Center\AnalyticsController;
use Modules\Centers\Http\Controllers\Center\AuthController;
use Modules\Centers\Http\Controllers\Center\BlockedClientController;
use Modules\Centers\Http\Controllers\Center\CenterController;
use Modules\Centers\Http\Controllers\Center\CenterUserController;
use Modules\Centers\Http\Controllers\Center\LaunchController;
use Modules\Centers\Http\Controllers\Center\ProfileCompletionController;
use Modules\Centers\Http\Controllers\Center\PurchaseController;
use Modules\Centers\Http\Controllers\Center\RatingController;
use Modules\Centers\Http\Controllers\Center\RegisterController;
use Modules\Centers\Http\Controllers\Center\TagController;
use Modules\Support\Http\Controllers\NotificationController;

Route::middleware(['api'])
    ->prefix('centers')
    ->name('centers.')
    ->group(function (): void {

        Route::post('/launch', LaunchController::class)
            ->middleware(['throttle:30,1'])
            ->name('launch');

        Route::middleware(['throttle:10,1'])
            ->group(function (): void {
                Route::post('/register', [RegisterController::class, 'store'])
                    ->name('register');

                Route::post('/register/phone', [RegisterController::class, 'sendCode'])
                    ->name('register.phone.send');
            });

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::middleware(['throttle:10,1'])
                    ->group(function (): void {
                        Route::post('/phone', [AuthController::class, 'login'])
                            ->name('phone.send');
                    });

                Route::post('/phone/verify', [AuthController::class, 'verify'])
                    ->name('phone.verify');
            });

        Route::middleware(['auth:center_user', 'set.locale.from.accept.language'])
            ->prefix('notifications')
            ->name('notifications.')
            ->group(function (): void {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
                Route::post('/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
            });

        Route::middleware(['auth:center_user'])
            ->group(function (): void {
                Route::get('/', [CenterController::class, 'index'])
                    ->name('index');

                Route::get('/tags', [TagController::class, 'index'])
                    ->name('tags.index');

                Route::post('/{center}/access', [CenterController::class, 'access'])
                    ->name('access');

                Route::middleware(['center.scope'])
                    ->group(function (): void {
                        Route::get('/{center}', [CenterController::class, 'show'])
                            ->name('show');

                        Route::match(['put', 'patch'], '/{center}', [CenterController::class, 'update'])
                            ->name('update');

                        Route::delete('/{center}', [AccountController::class, 'destroy'])
                            ->name('destroy');

                        Route::match(['put', 'patch'], '/{center}/profile', [AccountController::class, 'updateProfile'])
                            ->name('profile.update');

                        Route::match(['put', 'patch'], '/{center}/social-links', [CenterController::class, 'updateSocialLinks'])
                            ->name('social-links.update');

                        Route::match(['put', 'patch'], '/{center}/site-theme', [CenterController::class, 'updateSiteTheme'])
                            ->name('site-theme.update');

                        Route::get('/{center}/profile-completion', ProfileCompletionController::class)
                            ->name('profile-completion');

                        Route::get('/{center}/ratings', [RatingController::class, 'index'])
                            ->name('ratings');

                        Route::get('/{center}/blocked-clients', [BlockedClientController::class, 'index'])
                            ->name('blocked-clients.index');

                        Route::post('/{center}/blocked-clients', [BlockedClientController::class, 'store'])
                            ->name('blocked-clients.store');

                        Route::get('/{center}/analytics', AnalyticsController::class)
                            ->name('analytics');

                        Route::get('/{center}/purchases', [PurchaseController::class, 'index'])
                            ->name('purchases.index');

                        Route::post('/{center}/purchases/export', [PurchaseController::class, 'export'])
                            ->name('purchases.export');

                        Route::get('/{center}/purchases/code/{code}', [PurchaseController::class, 'showByCode'])
                            ->name('purchases.show-by-code');

                        Route::get('/{center}/purchases/{purchase}', [PurchaseController::class, 'show'])
                            ->name('purchases.show');

                        Route::prefix('/{center}/users')
                            ->name('users.')
                            ->group(function (): void {
                                Route::get('/', [CenterUserController::class, 'index'])
                                    ->name('index');

                                Route::get('/permissions', [CenterUserController::class, 'permissions'])
                                    ->name('permissions');

                                Route::post('/', [CenterUserController::class, 'store'])
                                    ->name('store');

                                Route::get('/{user}', [CenterUserController::class, 'show'])
                                    ->whereNumber('user')
                                    ->name('show');

                                Route::match(['put', 'patch'], '/{user}', [CenterUserController::class, 'update'])
                                    ->whereNumber('user')
                                    ->name('update');

                                Route::delete('/{user}', [CenterUserController::class, 'destroy'])
                                    ->whereNumber('user')
                                    ->name('destroy');
                            });
                    });
            });

    });
