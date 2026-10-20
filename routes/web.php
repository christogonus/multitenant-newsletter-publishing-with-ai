<?php

use App\Http\Controllers\Admin\BumpFileUploadController;
use App\Http\Controllers\Admin\EmailPreviewController;
use App\Http\Controllers\Auth\DeleteAccountController;
use App\Http\Controllers\Auth\WelcomeController;
use App\Http\Controllers\Dashboard\BumpFileController;
use App\Http\Controllers\Dashboard\IssueExportController;
use App\Http\Controllers\Dashboard\IssuePreviewController;
use App\Http\Controllers\Dashboard\SubscriberExportController;
use App\Http\Controllers\Dashboard\SwitchPublicationController;
use App\Http\Controllers\RedeemCodeController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard surface (app host)
|--------------------------------------------------------------------------
*/

Route::domain(config('newspub.app_host'))->group(function () {
    Route::redirect('/', '/dashboard');

    Route::view('/legal/terms', 'legal.terms')->name('legal.terms');
    Route::view('/legal/privacy', 'legal.privacy')->name('legal.privacy');

    // Public, so buyers whose inbox rejects our welcome email can still find their login details.
    Route::view('/access', 'access')->name('access');

    Route::get('/welcome/{user}/{hash}', [WelcomeController::class, 'show'])->middleware('signed')->name('welcome.show');
    Route::post('/welcome/{user}/{hash}', [WelcomeController::class, 'store'])->middleware('signed')->name('welcome.store');

    Route::get('/redeem/{reseller:reseller_slug?}', [RedeemCodeController::class, 'show'])->middleware('guest')->name('redeem.show');
    Route::post('/redeem/{reseller:reseller_slug?}', [RedeemCodeController::class, 'store'])->middleware(['guest', 'throttle:10,1'])->name('redeem.store');

    if (app()->environment('local', 'testing')) {
        Route::view('/styleguide', 'styleguide')->name('styleguide');
    }

    Route::middleware(['auth', 'not-suspended'])->group(function () {
        Route::view('/account', 'account.settings')->name('account');
        Route::delete('/account', DeleteAccountController::class)->middleware(['throttle:5,1', 'own-password'])->name('account.destroy');
        Route::view('/support', 'support')->name('support');
        Route::view('/upgrades', 'upgrades')->name('upgrades');
        Route::get('/downloads', Livewire\Downloads\Index::class)->name('downloads.index');
        Route::get('/downloads/files/{file}', [BumpFileController::class, 'show'])->name('downloads.file');
        Route::get('/downloads/{bumpOffer}/all', [BumpFileController::class, 'zip'])->middleware('throttle:10,1')->name('downloads.zip');

        Route::middleware('product:front_end')->group(function () {
            Route::get('/dashboard', Livewire\Dashboard::class)->name('dashboard');
            Route::get('/start', Livewire\Onboarding\CreatePublication::class)->name('newsletters.create');
            Route::post('/publications/{newsletter}/switch', SwitchPublicationController::class)->name('publications.switch');

            Route::middleware('publication')->group(function () {
                Route::get('/issues', Livewire\Issues\Index::class)->name('issues.index');
                Route::get('/issues/new', Livewire\Issues\Create::class)->name('issues.create');
                Route::get('/issues/{issue}', Livewire\Issues\Editor::class)->name('issues.edit');
                Route::get('/issues/{issue}/share', Livewire\Issues\Share::class)->name('issues.share');
                Route::get('/issues/{issue}/preview', IssuePreviewController::class)->name('issues.preview');

                Route::get('/readers', Livewire\Subscribers\Index::class)->name('subscribers.index');
                Route::get('/readers/export', SubscriberExportController::class)->name('subscribers.export');
                Route::get('/settings/publication', Livewire\Publication\Settings::class)->name('newsletters.settings');
                Route::get('/design', Livewire\Design\Index::class)->name('design.index');

                Route::middleware('product:oto1_delivery')->group(function () {
                    Route::get('/email-services', Livewire\Integrations\Index::class)->name('integrations.index');
                    Route::get('/issues/{issue}/export', IssueExportController::class)->name('issues.export');
                });

                Route::middleware('product:oto3_growth')->group(function () {
                    Route::get('/growth', Livewire\Growth\Index::class)->name('growth.index');
                    Route::get('/growth/pages/{leadPage}', Livewire\Growth\LeadPageEditor::class)->name('growth.pages.edit');
                });

                Route::get('/sponsors', Livewire\Sponsors\Index::class)->middleware('product:oto4_sponsor')->name('sponsors.index');
                Route::get('/social', Livewire\Social\Index::class)->middleware('product:oto6_social')->name('social.index');
                Route::get('/autopilot', Livewire\Autopilot\Index::class)->middleware('product:oto7_autopilot')->name('autopilot.index');
                Route::get('/network', Livewire\Network\Index::class)->middleware('product:oto10_network')->name('network.index');
            });

            Route::get('/kits', Livewire\Kits\Index::class)->middleware('product:oto5_dfy')->name('kits.index');
            Route::get('/publications', Livewire\Publications\Index::class)->middleware('product:oto9_multi')->name('publications.index');
            Route::get('/agency', Livewire\Agency\Index::class)->middleware('product:oto11_agency')->name('agency.index');
            Route::get('/reseller', Livewire\Reseller\Index::class)->middleware('product:oto12_reseller')->name('reseller.index');
            Route::get('/vault/{section?}', Livewire\Vault\Index::class)->middleware('product:bump_vault')->name('vault.index');
        });

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', Livewire\Admin\Users::class)->name('users');
            Route::get('/users/{user}/emails/welcome', [EmailPreviewController::class, 'welcome'])->name('emails.welcome');
            Route::get('/purchases/{purchase}/emails/upgrade', [EmailPreviewController::class, 'upgrade'])->name('emails.upgrade');
            Route::get('/reports', Livewire\Admin\Reports::class)->name('reports');
            Route::get('/spend', Livewire\Admin\Spend::class)->name('spend');
            Route::get('/content', Livewire\Admin\Content::class)->name('content');
            Route::get('/bumps', Livewire\Admin\Bumps::class)->name('bumps');
            Route::post('/bumps/{bumpOffer}/files', BumpFileUploadController::class)->name('bumps.files.store');
        });
    });
});
