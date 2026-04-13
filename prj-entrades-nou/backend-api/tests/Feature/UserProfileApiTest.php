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
            'name' => 'Nom',
            'email' => uniqid('p', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $g = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/user/profile');
        $g->assertOk();
        $g->assertJsonPath('name', 'Nom');
    }

    public function test_profile_patch_email_and_password(): void
    {
        $email = uniqid('patch', true).'@example.com';
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Patch',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $newEmail = uniqid('new', true).'@example.com';
        $patch = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/user/profile', [
                'name' => 'Patch Nou',
                'email' => $newEmail,
            ]);
        $patch->assertOk();
        $patch->assertJsonPath('email', $newEmail);
        $patch->assertJsonPath('name', 'Patch Nou');

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
