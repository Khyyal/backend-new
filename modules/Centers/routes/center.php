<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Center\AuthController;
use Modules\Centers\Http\Controllers\Center\BlockedClientController;
use Modules\Centers\Http\Controllers\Center\CenterController;
use Modules\Centers\Http\Controllers\Center\CenterUserController;
use Modules\Centers\Http\Controllers\Center\PurchaseController;
use Modules\Centers\Http\Controllers\Center\RatingController;
use Modules\Centers\Http\Controllers\Center\RegisterController;

Route::middleware(['api'])
    ->prefix('centers')
    ->name('centers.')
    ->group(function (): void {

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

        Route::middleware(['auth:center_user'])
            ->group(function (): void {
                Route::get('/', [CenterController::class, 'index'])
                    ->name('index');

                Route::post('/{center}/access', [CenterController::class, 'access'])
                    ->name('access');

                Route::middleware(['center.scope'])
                    ->group(function (): void {
                        Route::get('/{center}', [CenterController::class, 'show'])
                            ->name('show');

                        Route::match(['put', 'patch'], '/{center}', [CenterController::class, 'update'])
                            ->name('update');

                        Route::get('/{center}/ratings', [RatingController::class, 'index'])
                            ->name('ratings');

                        Route::get('/{center}/blocked-clients', [BlockedClientController::class, 'index'])
                            ->name('blocked-clients.index');

                        Route::post('/{center}/blocked-clients', [BlockedClientController::class, 'store'])
                            ->name('blocked-clients.store');

                        Route::get('/{center}/purchases', [PurchaseController::class, 'index'])
                            ->name('purchases.index');

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
