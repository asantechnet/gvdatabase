<?php

use App\Http\Controllers\AcademyClassController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('classes', [AcademyClassController::class, 'index'])->middleware('permission:view_academy_contracts');
    Route::get('classes/{academyClass}', [AcademyClassController::class, 'show'])->middleware('permission:view_academy_contracts');
    Route::post('classes', [AcademyClassController::class, 'store'])->middleware('permission:manage_academy_classes');
    Route::put('classes/{academyClass}', [AcademyClassController::class, 'update'])->middleware('permission:manage_academy_classes');
    Route::delete('classes/{academyClass}', [AcademyClassController::class, 'destroy'])->middleware('permission:manage_academy_classes');
});
