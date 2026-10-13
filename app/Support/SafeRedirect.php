<?php

namespace App\Support;

/**
 * Redirect targets that cannot be poisoned by the request that produced them.
 *
 * Laravel's `redirect()->intended()` replays the session's `url.intended`
 * verbatim, and `UrlGenerator::to()` returns an already-absolute URL unchanged —
 * scheme, host and all. That value is captured by `Redirector::guest()` from the
 * request's *full* URL, which on a deployment behind a TLS-terminating proxy is
 * `http://…` (PHP only ever sees the origin's plain-HTTP request).
 *
 * The result is a sign-in that cannot complete: the password is accepted, the
 * 302 points at `http://…/dashboard` on an `https://` page, and the browser
 * blocks it as mixed content — which Inertia surfaces as "Network Error".
 *
 * Reducing the stored value to a path lets the current request decide the scheme
 * and host, so the redirect always matches the page the user is actually on.
 * It also means a stale, spoofed or foreign host cannot bounce the user off-site.
 */
class SafeRedirect
{
    /**
     * Reduce a URL to a same-origin path (plus any query string).
     *
     * @param  string|null  $url       e.g. "http://host/dashboard?tab=1"
     * @param  string      $fallback   returned when there is no usable path
     */
    public static function toPath(?string $url, string $fallback = '/'): string
    {
        if ($url === null || trim($url) === '') {
            return $fallback;
        }

        $path = parse_url($url, PHP_URL_PATH);

        // Malformed input, or a URL with no path at all ("https://host").
        if (! is_string($path) || $path === '') {
            return $fallback;
        }

        // A leading "/" is what makes this a path rather than a bare segment,
        // and it neutralises protocol-relative input: "//evil.com/x" parses to
        // host "evil.com" with path "/x", so only "/x" survives.
        $path = '/'.ltrim($path, '/');

        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? $path.'?'.$query : $path;
    }
}
