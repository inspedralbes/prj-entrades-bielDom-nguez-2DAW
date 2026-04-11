<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class UserProfileApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp (): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        Cache::flush();
    }

    public function test_profile_and_settings (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Nom',
            'username' => 'prof_'.uniqid(),
            'email' => uniqid('p', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $g = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/user/profile');
        $g->assertOk();
        $g->assertJsonPath('gemini_personalization_enabled', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/user/settings', [
                'gemini_personalization_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('gemini_personalization_enabled', false);
    }
}
