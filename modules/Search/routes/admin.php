<?php

use Illuminate\Support\Facades\Route;
use Modules\Search\Http\Controllers\Admin\GlobalSearchController;

Route::get('admin/search', GlobalSearchController::class)
    ->middleware(['auth:system_user', 'throttle:60,1'])
    ->name('admin.search');
