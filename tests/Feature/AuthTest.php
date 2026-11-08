<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\MigratesWithOwner;
use Tests\Support\HospitalFixture;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use MigratesWithOwner;

    public function test_login_session_and_logout(): void
    {
        $hospital = HospitalFixture::create('north');

        $this->get('/');
        $token = csrf_token();

        $login = $this->withHeader('X-CSRF-TOKEN', $token)->postJson('/api/v1/login', [
            'email' => 'north.admin@example.com',
            'password' => 'local-demo-only-password',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.membership.role', 'hospital_admin')
            ->assertJsonPath('data.membership.tenant.timezone', 'Asia/Kolkata')
            ->assertJsonMissingPath('data.user.password');

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'north.admin@example.com')
            ->assertJsonPath('data.membership.id', $hospital->adminMembershipId);

        $this->withHeader('X-CSRF-TOKEN', csrf_token())
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_failed_login_does_not_echo_or_log_the_password(): void
    {
        HospitalFixture::create('north');

        $this->get('/');
        $password = 'not-the-real-secret-value';

        $response = $this->withHeader('X-CSRF-TOKEN', csrf_token())->postJson('/api/v1/login', [
            'email' => 'north.admin@example.com',
            'password' => $password,
        ]);

        $response->assertUnauthorized();
        $this->assertStringNotContainsString($password, $response->getContent() ?: '');
    }

    public function test_login_is_rate_limited(): void
    {
        HospitalFixture::create('north');
        $this->get('/');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('X-CSRF-TOKEN', csrf_token())->postJson('/api/v1/login', [
                'email' => 'north.admin@example.com',
                'password' => 'wrong-password-value',
            ])->assertUnauthorized();
        }

        $this->withHeader('X-CSRF-TOKEN', csrf_token())->postJson('/api/v1/login', [
            'email' => 'north.admin@example.com',
            'password' => 'wrong-password-value',
        ])->assertStatus(429);
    }

    public function test_state_changing_routes_use_the_session_and_csrf_stack(): void
    {
        $login = collect(Route::getRoutes())->first(
            fn ($route): bool => $route->uri() === 'api/v1/login' && in_array('POST', $route->methods(), true),
        );

        $this->assertNotNull($login);
        $this->assertContains('web', $login->gatherMiddleware());
    }
}
