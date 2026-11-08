<?php

use App\Http\Controllers\AcademyCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('durations', [AcademyCatalogController::class, 'durations'])->middleware('permission:view_academy_contracts');
    Route::patch('durations/{duration}', [AcademyCatalogController::class, 'updateDuration'])->middleware('permission:manage_academy_classes');
});
