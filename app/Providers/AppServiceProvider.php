<?php

namespace App\Providers;

use App\Ai\Contracts\NewsletterWriter;
use App\Ai\FakeNewsletterWriter;
use App\Ai\LaravelAiNewsletterWriter;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\EnsureProduct;
use App\Http\Middleware\EnsurePublication;
use App\Integrations\Certificates\CertificateProvisioner;
use App\Integrations\Certificates\ExternalCertificateProvisioner;
use App\Integrations\Certificates\PloiCertificateProvisioner;
use App\Integrations\PlatformManager;
use App\Livewire\Pulse\AiSpend;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NewsletterWriter::class, config('newspub.ai.fake') ? FakeNewsletterWriter::class : LaravelAiNewsletterWriter::class);
        $this->app->singleton(PlatformManager::class, fn ($app) => new PlatformManager($app));

        $this->app->bind(CertificateProvisioner::class, function () {
            $ploi = config('newspub.certificates.ploi');

            return config('newspub.certificates.driver') === 'ploi'
                ? new PloiCertificateProvisioner((string) $ploi['token'], (string) $ploi['server_id'], (string) $ploi['site_id'])
                : new ExternalCertificateProvisioner;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Feature::discover();

        Gate::define('viewPulse', fn (?User $user = null) => (bool) $user?->is_admin);
        Livewire::component('pulse.ai-spend', AiSpend::class);

        // Re-run the paid-feature and admin gates on every Livewire action, not only on page load,
        // so a refund or a removed admin flag takes effect in tabs that are already open.
        Livewire::addPersistentMiddleware([EnsureNotSuspended::class, EnsureProduct::class, EnsurePublication::class, EnsureAdmin::class]);

        RateLimiter::for('subscribe', function (Request $request) {
            $perMinute = (int) config('newspub.limits.subscribe_per_minute');

            return [
                Limit::perMinute($perMinute)->by('subscribe:'.$request->ip()),
                Limit::perDay($perMinute * 20)->by('subscribe-day:'.$request->ip()),
            ];
        });
    }
}
