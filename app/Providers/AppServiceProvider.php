<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a TLS-terminating CDN or reverse proxy, PHP sees the origin
        // request as plain HTTP, so `route()` and `url()` emit http:// links on
        // an https:// page. The browser blocks those as mixed content — the
        // post-login redirect in particular, which makes a correct password look
        // like a network failure. Trusting the proxy headers fixes scheme
        // detection (see TrustProxies); this is the backstop for when they do not
        // arrive. See config/app.php → force_https.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
