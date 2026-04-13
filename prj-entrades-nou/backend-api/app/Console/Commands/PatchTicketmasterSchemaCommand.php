<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bases creades abans d’afegir columnes TM (volums Docker antics) no re-execucionen init.sql.
 * Aquest patch és idempotent (IF NOT EXISTS).
 */
class PatchTicketmasterSchemaCommand extends Command
{
    protected $signature = 'db:patch-ticketmaster-schema';

    protected $description = 'Afegeix columnes/indexos Ticketmaster si falten (BD antiga vs database/init.sql)';

    public function handle (): int
    {
        $statements = [
            'ALTER TABLE venues ADD COLUMN IF NOT EXISTS external_tm_id VARCHAR(255)',
            'CREATE UNIQUE INDEX IF NOT EXISTS venues_external_tm_id_unique ON venues (external_tm_id) WHERE external_tm_id IS NOT NULL',
            'ALTER TABLE events ADD COLUMN IF NOT EXISTS external_tm_id VARCHAR(255)',
            'CREATE INDEX IF NOT EXISTS events_external_tm_id_index ON events (external_tm_id)',
            'ALTER TABLE events ADD COLUMN IF NOT EXISTS tm_sync_paused BOOLEAN NOT NULL DEFAULT FALSE',
            /* upgrade-spain-catalog-fields.sql — volums Docker creats abans d’aquest script */
            'ALTER TABLE events ADD COLUMN IF NOT EXISTS tm_url VARCHAR(500)',
            'ALTER TABLE events ADD COLUMN IF NOT EXISTS tm_category VARCHAR(100)',
            'ALTER TABLE events ADD COLUMN IF NOT EXISTS is_large_event BOOLEAN DEFAULT FALSE',
            'ALTER TABLE events ADD COLUMN IF NOT EXISTS image_url VARCHAR(1024)',
            'CREATE INDEX IF NOT EXISTS events_tm_category_index ON events (tm_category)',
            'CREATE INDEX IF NOT EXISTS events_is_large_event_index ON events (is_large_event)',
            'ALTER TABLE orders ADD COLUMN IF NOT EXISTS quantity INTEGER',
            'CREATE INDEX IF NOT EXISTS orders_quantity_index ON orders (quantity)',
        ];

        foreach ($statements as $sql) {
            DB::statement($sql);
        }

        /* seat_id nullable: només PostgreSQL; ignorem si ja és nullable */
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DO $$
BEGIN
  ALTER TABLE order_lines ALTER COLUMN seat_id DROP NOT NULL;
EXCEPTION
  WHEN OTHERS THEN NULL;
END $$;
SQL);
        }

        DB::statement('ALTER TABLE order_lines ADD COLUMN IF NOT EXISTS seat_key VARCHAR(255)');
        DB::statement('CREATE INDEX IF NOT EXISTS order_lines_seat_key_index ON order_lines (seat_key)');

        $this->info('Schema patch Ticketmaster aplicat.');

        return self::SUCCESS;
    }
}
