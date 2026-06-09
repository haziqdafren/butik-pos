<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesUsers;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_guest_sees_login_page(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_authenticated_user_can_still_see_login_page(): void
    {
        // No guest middleware on /login — the route returns 200 regardless of auth state
        $this->actingAs($this->cashier())->get('/login')->assertOk();
    }

    public function test_valid_credentials_log_in_cashier_and_redirect_to_kasir(): void
    {
        $this->cashier();

        $response = $this->post('/login', [
            'email'    => 'kasir@butik.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/kasir');
        $this->assertAuthenticated();
    }

    public function test_valid_credentials_log_in_owner_and_redirect_to_owner_dashboard(): void
    {
        $this->owner();

        $response = $this->post('/login', [
            'email'    => 'owner@butik.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/owner/dashboard');
        $this->assertAuthenticated();
    }

    public function test_wrong_password_returns_validation_error(): void
    {
        $this->cashier();

        $response = $this->post('/login', [
            'email'    => 'kasir@butik.test',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_missing_email_returns_validation_error(): void
    {
        $response = $this->post('/login', [
            'email'    => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guest_cannot_access_kasir_redirects_to_login(): void
    {
        $this->get('/kasir')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_owner_dashboard_redirects_to_login(): void
    {
        $this->get('/owner/dashboard')->assertRedirect('/login');
    }
}
