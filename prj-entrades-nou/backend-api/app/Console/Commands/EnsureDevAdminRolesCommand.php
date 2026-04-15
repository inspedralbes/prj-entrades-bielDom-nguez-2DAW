<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garanteix que l’usuari de desenvolupament admin@example.com tingui rols «admin» i «user».
 * Si algú s’ha registrat abans amb aquest correu, Spatie només tenia «user»; el panell /admin exigeix «admin».
 */
class EnsureDevAdminRolesCommand extends Command
{
    protected $signature = 'db:ensure-dev-admin-roles';

    protected $description = 'Assigna rols admin+user a admin@example.com (entorn local / Docker)';

    public function handle(): int
    {
        $email = 'admin@example.com';
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->warn('Usuari '.$email.' no trobat; s\'omet.');

            return self::SUCCESS;
        }

        // A. Sincronitza rols Spatie (substitueix la llista anterior per admin + user).
        $user->syncRoles(['admin', 'user']);

        // B. Evita cau de permisos antic en la mateixa petició / procés.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info('Rols sincronitzats per '.$email.' (admin, user).');

        return self::SUCCESS;
    }
}
