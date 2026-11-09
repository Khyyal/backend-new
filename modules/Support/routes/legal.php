<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\LegalPageController;

Route::get('/legal/{audience}/{type}', LegalPageController::class)->name('legal.show');
