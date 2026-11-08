<?php

use App\Http\Controllers\AcademyAttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('attendance', [AcademyAttendanceController::class, 'month'])->middleware('permission:view_academy_contracts');
    Route::put('attendance', [AcademyAttendanceController::class, 'save'])->middleware('permission:manage_academy_attendance');
    Route::get('teacher-timesheets', [AcademyAttendanceController::class, 'teacherMonth'])->middleware('permission:view_academy_contracts');
    Route::put('teacher-timesheets', [AcademyAttendanceController::class, 'saveTeacher'])->middleware('permission:manage_academy_attendance');
});
