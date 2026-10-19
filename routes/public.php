<?php

use App\Http\Controllers\Public\FeedController;
use App\Http\Controllers\Public\LeadPageController;
use App\Http\Controllers\Public\MembersController;
use App\Http\Controllers\Public\PublicationController;
use App\Http\Controllers\Public\ReferralController;
use App\Http\Controllers\Public\ReportController;
use App\Http\Controllers\Public\SubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public publication surface
|--------------------------------------------------------------------------
|
| The same routes are served three ways: on {subdomain}.public_root, on a
| verified customer domain (OTO 8), and under /p/{publication} on the app
| host for previews and local development. ResolvePublication finds the
| newsletter and only published issues are ever read here.
|
*/

$publicationRoutes = function (string $namePrefix) {
    Route::middleware('publication.public')->name($namePrefix)->group(function () {
        Route::get('/', [PublicationController::class, 'home'])->name('home');
        Route::get('/archive', [PublicationController::class, 'archive'])->name('archive');
        Route::get('/feed', [FeedController::class, 'rss'])->name('feed');
        Route::get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
        Route::get('/issues/{slug}', [PublicationController::class, 'issue'])->name('issue');
        Route::post('/subscribe', SubscribeController::class)->middleware('throttle:subscribe')->name('subscribe');
        Route::get('/thanks', [PublicationController::class, 'thanks'])->name('thanks');
        Route::post('/report', ReportController::class)->middleware('throttle:subscribe')->name('report');
        Route::get('/r/{code}', ReferralController::class)->name('referral');
        Route::get('/referrals/{subscriber}', [PublicationController::class, 'referrals'])->middleware('signed')->name('referrals');
        Route::get('/join/{leadPageSlug}', [LeadPageController::class, 'show'])->name('lead.show');
        Route::post('/join/{leadPageSlug}', [LeadPageController::class, 'store'])->middleware('throttle:subscribe')->name('lead.store');
        Route::get('/join/{leadPageSlug}/gift/{subscriber}', [LeadPageController::class, 'gift'])->middleware('signed')->name('lead.gift');

        Route::get('/members', [MembersController::class, 'show'])->name('members');
        Route::post('/members/code', [MembersController::class, 'sendCode'])->middleware('throttle:subscribe')->name('members.code');
        Route::post('/members/verify', [MembersController::class, 'verify'])->middleware('throttle:subscribe')->name('members.verify');
        Route::post('/members/sign-out', [MembersController::class, 'signOut'])->name('members.sign-out');
        Route::post('/members/checkout', [MembersController::class, 'checkout'])->middleware('throttle:subscribe')->name('members.checkout');
        Route::post('/members/paid', [MembersController::class, 'claimManualPayment'])->middleware('throttle:subscribe')->name('members.paid');
        Route::get('/members/complete/{provider}', [MembersController::class, 'complete'])->name('members.complete');
    });
};

Route::domain(config('newspub.app_host'))->prefix('p/{publication}')->group(fn () => $publicationRoutes('preview.'));

Route::domain('{publication}.'.config('newspub.public_root'))->group(fn () => $publicationRoutes('public.'));

Route::domain('{customHost}')->where(['customHost' => '[A-Za-z0-9.-]+'])->group(fn () => $publicationRoutes('custom.'));
