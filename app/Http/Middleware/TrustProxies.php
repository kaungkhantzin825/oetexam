<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Deployment sits behind a CDN that terminates TLS and forwards to the
     * origin over plain HTTP, so the request PHP receives is always `http`.
     * Without trusting that proxy, Laravel reads the scheme from the origin
     * request, believes every visitor is on http://, and generates http:// URLs
     * — which the browser then blocks as mixed content. The visible symptom is
     * that sign-in never completes: the POST succeeds, the 302 points at
     * `http://…/dashboard`, and Inertia reports a network error.
     *
     * `*` trusts whichever hop forwarded the request, which is what the standard
     * X-Forwarded-* headers need when the proxy addresses are not fixed (a CDN's
     * are not, and they change). If this origin is also reachable directly, and
     * you would rather not trust forwarded headers from just anyone, replace it
     * with the proxy's IPs/CIDRs, e.g. `['10.0.0.0/8', '172.16.0.0/12']`.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
