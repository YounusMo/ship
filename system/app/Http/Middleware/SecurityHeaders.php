<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defensive HTTP response headers to every web response.
 *
 * Headers added:
 *   Strict-Transport-Security      — opt browsers into HTTPS only
 *   X-Content-Type-Options         — disable MIME sniffing
 *   X-Frame-Options                — block clickjacking (SAMEORIGIN
 *                                    so the print-preview iframes in
 *                                    the admin still work)
 *   Referrer-Policy                — strip URL leakage to third parties
 *   Content-Security-Policy        — permissive baseline; tighten over
 *                                    time as the team validates what
 *                                    breaks
 *
 * HSTS is only emitted under https. On http (local dev) it would be
 * ignored anyway, but emitting it would be misleading.
 *
 * The CSP intentionally allows 'unsafe-inline' for both scripts and
 * styles because the legacy Blade views and jQuery inline handlers
 * still rely on it. Tightening this is tracked separately — for now
 * the goal is hardening everything around the CSP so the eventual
 * tightening is a one-line change.
 *
 * @see docs/GAPS.md gap #19
 */
class SecurityHeaders
{
    /** @var array<string, string> */
    private const STATIC_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'SAMEORIGIN',
        'Referrer-Policy'        => 'same-origin',
    ];

    private const CSP =
        "default-src 'self'; "
        ."img-src 'self' data: blob: https:; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."font-src 'self' data:; "
        ."connect-src 'self'; "
        ."frame-ancestors 'self'; "
        ."base-uri 'self'; "
        ."form-action 'self';";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::STATIC_HEADERS as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        // Only emit HSTS on actual https requests. Browsers ignore it on
        // http, but emitting it there would lie about the deployment.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                false,
            );
        }

        // Don't clobber a stricter CSP that may have been set upstream
        // (e.g. proforma PDF download endpoints).
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::CSP);
        }

        return $response;
    }
}
