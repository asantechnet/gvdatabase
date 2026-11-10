<?php

use App\Http\Controllers\CrmRelationshipController;
use Illuminate\Support\Facades\Route;

/*
 * Global and case-scoped relationship surface for universities and countries.
 * Existing visitor/applicant relationship endpoints stay on their original
 * `/crm` routes; this file only adds the catalogue relationship family and the
 * applicant case-record aggregate.
 */
Route::get('applicants/{applicant}/relationships', [CrmRelationshipController::class, 'applicantRecords'])
    ->whereNumber('applicant');

Route::prefix('crm-relationships/{type}/{id}')
    ->whereIn('type', ['university', 'country'])
    ->whereNumber('id')
    ->group(function () {
        Route::get('/', [CrmRelationshipController::class, 'show']);
        Route::patch('/notes', [CrmRelationshipController::class, 'updateNotes']);
        Route::post('/interactions', [CrmRelationshipController::class, 'storeInteraction']);
    });
