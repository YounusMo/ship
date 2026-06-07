<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\TestCase;

/**
 * Smoke tests for the SecurityHeaders middleware.
 *
 * @see docs/GAPS.md gap #19
 */
class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_appear_on_responses(): void
    {
        $r = $this->get('/up');
        $r->assertStatus(200);

        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $r->assertHeader('Referrer-Policy', 'same-origin');
        $r->assertHeader('Content-Security-Policy');
        $this->assertStringContainsString(
            "default-src 'self'",
            (string) $r->headers->get('Content-Security-Policy'),
        );
    }

    public function test_hsts_only_on_https_requests(): void
    {
        // Plain http request — no HSTS.
        $r = $this->get('/up');
        $this->assertFalse($r->headers->has('Strict-Transport-Security'));

        // Simulate the production posture: trusted proxy forwards
        // X-Forwarded-Proto: https. TrustProxies(*) propagates this to
        // $request->isSecure() => true.
        $r = $this->withHeader('X-Forwarded-Proto', 'https')->get('/up');
        $r->assertHeader('Strict-Transport-Security');
        $this->assertStringContainsString(
            'max-age=31536000',
            (string) $r->headers->get('Strict-Transport-Security'),
        );
    }
}
