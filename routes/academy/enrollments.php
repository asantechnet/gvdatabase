<?php

use App\Http\Controllers\AcademyEnrollmentController;
use App\Http\Controllers\AcademyStudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('enrollments', [AcademyEnrollmentController::class, 'index'])->middleware('permission:view_academy_contracts');
    Route::get('enrollments/{academyEnrollment}', [AcademyEnrollmentController::class, 'show'])->middleware('permission:view_academy_contracts');
    Route::get('enrollments/{academyEnrollment}/slip', [AcademyEnrollmentController::class, 'slip'])->middleware('permission:view_academy_contracts');
    Route::patch('enrollments/{academyEnrollment}', [AcademyEnrollmentController::class, 'update'])->middleware('permission:manage_academy_classes');
    Route::get('students/{student}/finance', [AcademyStudentController::class, 'finance'])
        ->middleware('permission:view_applicant_financials,view_all_finance_records,view_incomes');
});
