<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // This is an API + SPA application; the web root has no page.
        // Verify the API layer boots and enforces auth (401, not 500).
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}
