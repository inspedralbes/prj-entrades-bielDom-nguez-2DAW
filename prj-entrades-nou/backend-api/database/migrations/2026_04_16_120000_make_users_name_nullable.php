<?php

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

return new class extends Migration
{
    /**
     * El registre ja no omple `name`; es manté la columna per compatibilitat amb dades antigues.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN name DROP NOT NULL');
        }
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("UPDATE users SET name = username WHERE name IS NULL OR trim(name) = ''");
            DB::statement('ALTER TABLE users ALTER COLUMN name SET NOT NULL');
        }
        if ($driver === 'mysql') {
            DB::statement("UPDATE users SET name = username WHERE name IS NULL OR trim(name) = ''");
            DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NOT NULL');
        }
    }
};
