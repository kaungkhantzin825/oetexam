<?php

namespace Tests\Unit;

use App\Support\SafeRedirect;
use Tests\TestCase;

/**
 * Pins the redirect-target normaliser behind the sign-in flow.
 *
 * The bug it exists to prevent: `redirect()->intended()` replays the stored
 * `url.intended` verbatim — and `UrlGenerator::to()` returns an already-absolute
 * URL unchanged. On a deployment where PHP only ever sees the origin's plain
 * HTTP request (TLS terminated by a CDN) that stored value is `http://…`, so the
 * browser blocks the post-login redirect as mixed content and sign-in looks like
 * a network failure even though the password was accepted.
 */
class SafeRedirectTest extends TestCase
{
    /** The exact shape the deployment bug produced. */
    public function test_a_stored_http_url_is_reduced_to_its_path(): void
    {
        $this->assertSame(
            '/dashboard',
            SafeRedirect::toPath('http://oetexam.mmcertify.com/dashboard')
        );
    }

    public function test_an_https_url_is_reduced_to_its_path(): void
    {
        $this->assertSame(
            '/dashboard',
            SafeRedirect::toPath('https://oetexam.mmcertify.com/dashboard')
        );
    }

    public function test_the_query_string_survives(): void
    {
        $this->assertSame(
            '/practice/3?tab=writing',
            SafeRedirect::toPath('http://host/practice/3?tab=writing')
        );
    }

    /** A foreign host must not be able to bounce the user off-site. */
    public function test_a_foreign_host_is_dropped(): void
    {
        $this->assertSame('/dashboard', SafeRedirect::toPath('https://evil.example/dashboard'));
    }

    /**
     * "//evil.example/dashboard" is protocol-relative: parse_url reads
     * evil.example as the host, so only the path survives.
     */
    public function test_a_protocol_relative_url_keeps_only_its_path(): void
    {
        $this->assertSame('/dashboard', SafeRedirect::toPath('//evil.example/dashboard'));
    }

    public function test_a_relative_path_is_returned_unchanged(): void
    {
        $this->assertSame('/dashboard', SafeRedirect::toPath('/dashboard'));
    }

    /** A bare segment must still be anchored, or it could read as a host. */
    public function test_a_bare_segment_is_anchored_to_the_root(): void
    {
        $this->assertSame('/dashboard', SafeRedirect::toPath('dashboard'));
    }

    public function test_absent_or_empty_input_falls_back(): void
    {
        $this->assertSame('/fallback', SafeRedirect::toPath(null, '/fallback'));
        $this->assertSame('/fallback', SafeRedirect::toPath('', '/fallback'));
        $this->assertSame('/fallback', SafeRedirect::toPath('   ', '/fallback'));
    }

    /** A host with no path has nothing to redirect to. */
    public function test_a_host_without_a_path_falls_back(): void
    {
        $this->assertSame(
            '/fallback',
            SafeRedirect::toPath('https://oetexam.mmcertify.com', '/fallback')
        );
    }

    /**
     * Whatever comes back is always a rooted path, so nothing the browser sees
     * can be read as a scheme.
     */
    public function test_the_result_is_always_a_rooted_path(): void
    {
        foreach ([
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'https://evil.example',
            '//evil.example',
            'http://user:pass@evil.example/dashboard',
        ] as $hostile) {
            $this->assertStringStartsWith('/', SafeRedirect::toPath($hostile, '/dashboard'));
        }
    }
}
