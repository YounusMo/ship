<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\RequireType;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Unit tests for the RequireType middleware. We exercise it directly
 * with a synthetic user object rather than spinning up the full HTTP
 * stack — keeps the test fast and independent of MySQL.
 *
 * @see docs/GAPS.md gap #3
 */
class RequireTypeMiddlewareTest extends TestCase
{
    public function test_admin_passes_when_admin_required(): void
    {
        $request = $this->requestAs('admin');
        $middleware = new RequireType();
        $r = $middleware->handle($request, fn () => response('ok'), 'admin');
        $this->assertSame(200, $r->getStatusCode());
    }

    public function test_branch_admin_blocked_when_only_admin_allowed(): void
    {
        $request = $this->requestAs('branch_admin');
        $middleware = new RequireType();
        $r = $middleware->handle($request, fn () => response('ok'), 'admin');
        $this->assertSame(403, $r->getStatusCode());
    }

    public function test_branch_admin_passes_when_admin_or_branch_admin_allowed(): void
    {
        $request = $this->requestAs('branch_admin');
        $middleware = new RequireType();
        $r = $middleware->handle($request, fn () => response('ok'), 'admin', 'branch_admin');
        $this->assertSame(200, $r->getStatusCode());
    }

    public function test_unauthenticated_request_is_401(): void
    {
        $request = Request::create('/anything', 'GET');
        // No user attached.
        $middleware = new RequireType();
        $r = $middleware->handle($request, fn () => response('ok'), 'admin');
        $this->assertSame(401, $r->getStatusCode());
    }

    public function test_unknown_type_value_is_denied(): void
    {
        $request = $this->requestAs('something_made_up');
        $middleware = new RequireType();
        $r = $middleware->handle($request, fn () => response('ok'), 'admin', 'branch_admin');
        $this->assertSame(403, $r->getStatusCode());
    }

    private function requestAs(string $type): Request
    {
        $request = Request::create('/anything', 'GET');
        $user = new class($type) {
            public function __construct(public readonly string $type) {}
        };
        $request->setUserResolver(fn () => $user);
        return $request;
    }
}
