<?php

namespace App\Providers;

use App\Ai\Contracts\NewsletterWriter;
use App\Ai\LaravelAiNewsletterWriter;
use App\Integrations\PlatformManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NewsletterWriter::class, LaravelAiNewsletterWriter::class);
        $this->app->singleton(PlatformManager::class, fn ($app) => new PlatformManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Feature::discover();

        RateLimiter::for('subscribe', function (Request $request) {
            $perMinute = (int) config('newspub.limits.subscribe_per_minute');

            return [
                Limit::perMinute($perMinute)->by('subscribe:'.$request->ip()),
                Limit::perDay($perMinute * 20)->by('subscribe-day:'.$request->ip()),
            ];
        });
    }
}
