<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_route_redirects_to_admin_login_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin/login');
    }
}
