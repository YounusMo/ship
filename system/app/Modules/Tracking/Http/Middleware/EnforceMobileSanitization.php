<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Last line of defense for white-label sanitization.
 *
 * Scans the JSON response body for forbidden patterns (e.g. provider
 * names like "shipsgo"). Behavior is environment-dependent — see
 * docs/ALIGNMENT_PATCH.md §2.8.
 *
 *   local / testing  → throws RuntimeException so CI fails loud and
 *                      we catch the leak before it ships.
 *   production       → log + alert + scrub the offending substring out
 *                      and serve the response. Never break a customer
 *                      read because of a string leak.
 *
 * Patterns and which envs throw vs scrub are configured in
 * config/tracking.php under `sanitization`.
 */
class EnforceMobileSanitization
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'json')) {
            return $response;
        }

        $body = (string) $response->getContent();
        if ($body === '') {
            return $response;
        }

        $patterns = (array) config('tracking.sanitization.forbidden_patterns', []);
        $hits = [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $m)) {
                $hits[] = ['pattern' => $pattern, 'sample' => $m[0]];
            }
        }
        if ($hits === []) {
            return $response;
        }

        $env = app()->environment();
        $throwEnvs = (array) config('tracking.sanitization.throw_envs', ['local', 'testing']);

        if (in_array($env, $throwEnvs, true)) {
            throw new \RuntimeException(
                'Mobile sanitization tripwire: response contains forbidden pattern(s) '
                . json_encode($hits)
                . ' on ' . $request->method() . ' ' . $request->path(),
            );
        }

        // Production path — log, alert, scrub, serve.
        Log::warning('mobile_sanitization_leak', [
            'path'        => $request->path(),
            'hits'        => $hits,
            'tokenable_id' => optional($request->user())->getKey(),
        ]);

        $scrubbed = $body;
        foreach ($hits as $hit) {
            $scrubbed = (string) preg_replace($hit['pattern'], '[redacted]', $scrubbed);
        }
        $response->setContent($scrubbed);

        return $response;
    }
}
