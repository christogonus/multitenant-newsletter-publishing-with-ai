<?php

use App\Http\Controllers\Internal\DomainAllowedController;
use App\Http\Controllers\Internal\DomainCertificateCallbackController;
use App\Http\Controllers\Internal\LaunchpadIpnController;
use App\Http\Controllers\Public\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal surface (app host)
|--------------------------------------------------------------------------
|
| The purchase IPN, the proxy's "is this domain allowed?" check for
| on-demand TLS, and signed reader links (unsubscribe and delete data).
|
*/

Route::domain(config('newspub.app_host'))->group(function () {
    Route::post('/ipn/launchpad', LaunchpadIpnController::class)->middleware('throttle:120,1')->name('ipn.launchpad');

    Route::get('/internal/domains/allowed', DomainAllowedController::class)->middleware('throttle:600,1')->name('internal.domains.allowed');
    Route::post('/internal/domains/{domain}/certificate-callback', DomainCertificateCallbackController::class)
        ->middleware(['signed', 'throttle:60,1'])
        ->name('internal.domains.certificate-callback');

    Route::get('/readers/{subscriber}/unsubscribe', [UnsubscribeController::class, 'show'])->middleware('signed')->name('readers.unsubscribe');
    Route::post('/readers/{subscriber}/unsubscribe', [UnsubscribeController::class, 'store'])->middleware('signed')->name('readers.unsubscribe.store');
    Route::post('/readers/{subscriber}/delete', [UnsubscribeController::class, 'destroy'])->middleware('signed')->name('readers.delete');
});
