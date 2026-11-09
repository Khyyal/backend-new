<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Docs\GuideController;

Route::get('/guide/{path?}', [GuideController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['auth:system_user_web', 'can:viewApiDocs'])
    ->name('guide.show');
