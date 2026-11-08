<?php

use App\Http\Controllers\AcademyCertificateController;
use Illuminate\Support\Facades\Route;

Route::prefix('academy')->group(function () {
    Route::get('certificates', [AcademyCertificateController::class, 'index'])->middleware('permission:view_academy_contracts');
    Route::get('certificates/{academyCertificate}', [AcademyCertificateController::class, 'show'])->middleware('permission:view_academy_contracts');
    Route::post('certificates', [AcademyCertificateController::class, 'store'])->middleware('permission:issue_academy_certificates');
    Route::patch('certificates/{academyCertificate}/revoke', [AcademyCertificateController::class, 'revoke'])->middleware('permission:issue_academy_certificates');
    Route::get('certificate-templates', [AcademyCertificateController::class, 'templates'])->middleware('permission:view_academy_contracts');
    Route::patch('certificate-templates/{template}', [AcademyCertificateController::class, 'updateTemplate'])->middleware('permission:issue_academy_certificates');
});
