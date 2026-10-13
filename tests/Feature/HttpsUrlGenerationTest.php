<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * URL scheme detection for a deployment behind a TLS-terminating CDN.
 *
 * The origin only ever receives plain HTTP — the CDN does the TLS — so Laravel
 * has to learn the visitor's scheme from X-Forwarded-Proto. When it does not
 * (TrustProxies trusted nothing), every generated URL came out as http:// on an
 * https:// page. The visible failure was sign-in: the POST succeeded, the 302
 * pointed at `http://…/dashboard`, and the browser blocked it as mixed content,
 * which Inertia surfaced as "Network Error".
 *
 * An unauthenticated GET to a signed-in route is the cheapest way to inspect an
 * absolutely-generated URL without needing a user: `auth` runs before `active`,
 * so it redirects without touching the database.
 */
class HttpsUrlGenerationTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceScheme(null);
        Request::setTrustedProxies([], 0);

        parent::tearDown();
    }

    private function redirectLocationFor(?string $forwardedProto): string
    {
        $headers = $forwardedProto ? ['X-Forwarded-Proto' => $forwardedProto] : [];

        $response = $this->withHeaders($headers)->get('/dashboard');
        $response->assertRedirect();

        return (string) $response->headers->get('Location');
    }

    public function test_the_forwarded_https_scheme_is_trusted(): void
    {
        $this->assertStringStartsWith('https://', $this->redirectLocationFor('https'));
    }

    /**
     * The contrast that made this a deployment-only bug. Without the header the
     * app believes the visitor is on http and generates http:// URLs — which is
     * precisely what the browser refuses to follow from an https:// page.
     */
    public function test_without_the_forwarded_header_laravel_sees_plain_http(): void
    {
        $this->assertStringStartsWith('http://', $this->redirectLocationFor(null));
    }

    /** The backstop, for when the proxy headers never arrive at all. */
    public function test_force_https_makes_generated_urls_https_regardless(): void
    {
        config(['app.force_https' => true]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', $this->redirectLocationFor(null));
    }

    /**
     * Local development and this suite run over plain http, so the default has
     * to stay off outside production — otherwise `php artisan serve` would hand
     * out https:// links and no asset would load.
     */
    public function test_force_https_is_off_outside_production(): void
    {
        $this->assertNotSame('production', config('app.env'));
        $this->assertFalse(config('app.force_https'));
    }
}
