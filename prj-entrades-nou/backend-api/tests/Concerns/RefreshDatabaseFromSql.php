<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Mateix comportament que RefreshDatabase però l'esquema ve de database/testing/schema.sqlite.sql
 * (monorepo), sense migracions PHP.
 */
trait RefreshDatabaseFromSql
{
    use RefreshDatabase {
        migrateDatabases as protected migrateDatabasesFromMigrations;
    }

    protected function migrateDatabases(): void
    {
        $path = base_path('../database/testing/schema.sqlite.sql');
        $resolved = realpath($path);
        if ($resolved === false || !is_readable($resolved)) {
            throw new \RuntimeException(
                'No es troba o no es pot llegir database/testing/schema.sqlite.sql (ruta esperada des de backend-api: '.$path.').'
            );
        }

        $this->artisan('db:wipe', ['--force' => true, '--no-interaction' => true]);

        $sql = File::get($resolved);

        foreach ($this->splitSqlStatements($sql) as $statement) {
            if ($statement !== '') {
                DB::unprepared($statement);
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function splitSqlStatements(string $sql): array
    {
        $parts = preg_split('/;\s*(?=(?:[^\'"]*(?:"[^"]*"|\'[^\']*\')[^\'"]*)*$)/', $sql);

        return array_values(array_filter(array_map(static fn (string $p): string => trim($p), $parts ?: [])));
    }
}
