<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_register_returns_token_and_me_works(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Prova',
            'username' => 'provauser',
            'email' => 'prova@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $me = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $me->assertOk();
        $me->assertJsonPath('email', 'prova@example.com');
        $me->assertJsonPath('username', 'provauser');
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }
}
