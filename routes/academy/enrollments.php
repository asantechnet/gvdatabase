<?php

use App\Http\Controllers\AcademyEnrollmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('enrollments', [AcademyEnrollmentController::class, 'index'])->middleware('permission:view_academy_contracts');
    Route::get('enrollments/{academyEnrollment}', [AcademyEnrollmentController::class, 'show'])->middleware('permission:view_academy_contracts');
    Route::patch('enrollments/{academyEnrollment}', [AcademyEnrollmentController::class, 'update'])->middleware('permission:manage_academy_classes');
});
