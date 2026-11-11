<?php

use App\Http\Controllers\AcademyAttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('attendance', [AcademyAttendanceController::class, 'month'])->middleware('permission:view_academy_attendance');
    Route::put('attendance', [AcademyAttendanceController::class, 'save'])->middleware('permission:manage_academy_attendance');
    Route::delete('attendance/{academyAttendance}', [AcademyAttendanceController::class, 'destroy'])->middleware('permission:manage_academy_attendance');
});
