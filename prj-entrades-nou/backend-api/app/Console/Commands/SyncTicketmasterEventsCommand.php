<?php

namespace App\Console\Commands;

//================================ NAMESPACES / IMPORTS ============

use App\Services\Ticketmaster\TicketmasterEventImportService;
use Illuminate\Console\Command;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class SyncTicketmasterEventsCommand extends Command
{
    protected $signature = 'ticketmaster:sync-events
                            {--pages= : Màxim de pàgines Discovery a recórrer (sobreesciu config)}';

    protected $description = 'Sincronitza esdeveniments i recintes des de Ticketmaster Discovery cap a les taules locals.';

    public function handle(TicketmasterEventImportService $importService): int
    {
        $maxPages = null;
        if ($this->option('pages') !== null) {
            $maxPages = (int) $this->option('pages');
        }

        $this->info('Inici sincronització Ticketmaster…');
        $result = $importService->sync($maxPages);

        $this->line('Inserits: '.$result['inserted']);
        $this->line('Existents (ignorats): '.$result['skipped_existing']);
        $this->line('Sense recinte TM: '.$result['skipped_no_venue']);
        $this->line('Pàgines llegides: '.$result['pages_fetched']);

        foreach ($result['errors'] as $msg) {
            $this->warn($msg);
        }

        return self::SUCCESS;
    }
}
