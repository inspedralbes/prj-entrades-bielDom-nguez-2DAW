<?php

namespace Database\Seeders;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class RoleSeeder extends Seeder
{
    /**
     * Rols interns (guard web / api segons config): user, validator, admin.
     */
    public function run(): void
    {
        $guard = 'web';

        Role::query()->firstOrCreate(
            ['name' => 'user', 'guard_name' => $guard],
        );
        Role::query()->firstOrCreate(
            ['name' => 'validator', 'guard_name' => $guard],
        );
        Role::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => $guard],
        );
    }
}
