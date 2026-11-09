<?php

use App\Http\Controllers\AcademyClassController;
use App\Http\Controllers\AcademyStudentController;
use App\Http\Controllers\AcademyTeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('classes', [AcademyClassController::class, 'index'])->middleware('permission:view_academy_attendance');
    Route::get('classes/{academyClass}', [AcademyClassController::class, 'show'])->middleware('permission:view_academy_attendance');
    Route::post('classes', [AcademyClassController::class, 'store'])->middleware('permission:manage_academy_classes');
    Route::put('classes/{academyClass}', [AcademyClassController::class, 'update'])->middleware('permission:manage_academy_classes');
    Route::patch('classes/{academyClass}/complete', [AcademyClassController::class, 'complete'])->middleware('permission:manage_academy_attendance');
    Route::delete('classes/{academyClass}', [AcademyClassController::class, 'destroy'])->middleware('permission:manage_academy_classes');
    Route::get('teachers', [AcademyTeacherController::class, 'index'])->middleware('permission:view_academy_attendance');
    Route::post('students', [AcademyStudentController::class, 'store'])->middleware('permission:manage_academy_classes');
});
