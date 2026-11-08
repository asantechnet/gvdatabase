<?php

use App\Http\Controllers\AcademyOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('overview', AcademyOverviewController::class)->middleware('permission:view_academy_contracts');
});
