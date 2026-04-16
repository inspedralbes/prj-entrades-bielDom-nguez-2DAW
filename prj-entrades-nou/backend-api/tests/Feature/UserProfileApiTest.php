<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class UserProfileApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_profile(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'username' => 'profil_u_'.uniqid(),
            'email' => uniqid('p', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');
        $expectedUsername = $reg->json('user.username');

        $g = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/user/profile');
        $g->assertOk();
        $g->assertJsonPath('username', $expectedUsername);
        $g->assertJsonPath('name', $expectedUsername);
    }

    public function test_profile_patch_username_and_email(): void
    {
        $email = uniqid('patch', true).'@example.com';
        $reg = $this->postJson('/api/auth/register', [
            'username' => 'patch_u_'.uniqid(),
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $newEmail = uniqid('new', true).'@example.com';
        $newUsername = 'patch_new_'.uniqid();
        $patch = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/user/profile', [
                'username' => $newUsername,
                'email' => $newEmail,
            ]);
        $patch->assertOk();
        $patch->assertJsonPath('email', $newEmail);
        $patch->assertJsonPath('username', $newUsername);
        $patch->assertJsonPath('name', $newUsername);

        $bad = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/user/profile', [
                'password' => 'noupassword456',
                'password_confirmation' => 'noupassword456',
            ]);
        $bad->assertStatus(422);

        $ok = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/user/profile', [
                'current_password' => 'password123',
                'password' => 'noupassword456',
                'password_confirmation' => 'noupassword456',
            ]);
        $ok->assertOk();
    }
}
