<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL is gated behind auth and redirects unauthenticated
     * visitors to the login screen. We assert the redirect rather than a
     * 200 so the stock Flutter/Laravel scaffold test reflects the actual
     * application behavior.
     */
    public function test_root_redirects_unauthenticated_visitors_to_login(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
