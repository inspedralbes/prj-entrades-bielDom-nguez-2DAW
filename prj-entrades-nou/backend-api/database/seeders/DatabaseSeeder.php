<?php

namespace Database\Seeders;

//================================ NAMESPACES / IMPORTS ============

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
        $user->assignRole('user');
    }
}
