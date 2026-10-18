<?php

use App\Http\Controllers\PwaManifestController;
use Illuminate\Support\Facades\Route;

// Must precede the SPA catch-all, which answers every non-api path.
Route::get('manifest.webmanifest', PwaManifestController::class)->name('pwa.manifest');

Route::get('{any?}', function () {
    return view('application');
})->where('any', '^(?!api).*$');
