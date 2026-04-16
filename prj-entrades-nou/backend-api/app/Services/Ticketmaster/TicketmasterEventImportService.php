<?php

namespace App\Services\Ticketmaster;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Services\Ticketmaster\Mapping\TicketmasterDiscoveryEventMapper;
use App\Services\Ticketmaster\Venue\TicketmasterVenueUpsertService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Importa o actualitza `venues` i `events` des de Ticketmaster Discovery (id extern `external_tm_id`).
 * Si `events.tm_sync_paused` és cert, no s'actualitza la fila (edicions locals / administrador).
 */
class TicketmasterEventImportService
{
    public function __construct(
        private readonly TicketmasterDiscoveryEventsClient $discoveryClient,
        private readonly TicketmasterDiscoveryEventMapper $eventMapper,
        private readonly TicketmasterVenueUpsertService $venueUpsert,
    ) {}

    /**
     * A. Paginació Discovery i recollida d’errors.
     * B. Per cada ítem: venue + upsert event (filtres categoria/capacitat).
     * C. Resum i log.
     *
     * @return array{
     *   inserted: int,
     *   skipped_no_venue: int,
     *   skipped_existing: int,
     *   pages_fetched: int,
     *   errors: array<int, string>
     * }
     */
    public function sync(?int $maxPagesOverride = null): array
    {
        $inserted = 0;
        $skippedNoVenue = 0;
        $skippedExisting = 0;
        $pagesFetched = 0;
        $errors = [];

        $country = (string) Config::get('services.ticketmaster.sync_country', 'ES');
        $size = (int) Config::get('services.ticketmaster.sync_page_size', 100);
        $maxPages = $maxPagesOverride;
        if ($maxPages === null) {
            $maxPages = (int) Config::get('services.ticketmaster.sync_max_pages', 20);
        }
        if ($maxPages < 1) {
            $maxPages = 1;
        }

        $page = 0;
        while ($page < $maxPages) {
            $payload = $this->discoveryClient->fetchEventsPage($page, $size, $country);
            if ($payload === null) {
                $errors[] = 'Petició Discovery buida o fallida a la pàgina '.$page;
                break;
            }

            $pagesFetched++;

            $eventRows = $payload['events'];
            foreach ($eventRows as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $extEventId = null;
                if (isset($item['id']) && is_string($item['id'])) {
                    $extEventId = $item['id'];
                }
                if ($extEventId === null || $extEventId === '') {
                    continue;
                }

                $venuePayload = $this->eventMapper->extractFirstVenue($item);
                if ($venuePayload === null) {
                    $skippedNoVenue++;

                    continue;
                }

                $venue = $this->venueUpsert->upsertVenueFromPayload($venuePayload);
                if ($venue === null) {
                    $skippedNoVenue++;

                    continue;
                }

                $name = $this->eventMapper->extractEventName($item);
                $startsAt = $this->eventMapper->extractStartsAt($item);
                $category = $this->eventMapper->extractCategory($item);

                if ($startsAt === null) {
                    $errors[] = 'Sense data d\'inici per esdeveniment '.$extEventId;

                    continue;
                }

                $tmCategory = $this->eventMapper->extractAndMapCategory($item);
                if ($tmCategory === null) {
                    $skippedExisting++;

                    continue;
                }

                if (! $this->eventMapper->isLargeEvent($item)) {
                    $skippedExisting++;

                    continue;
                }

                $posterUrl = $this->eventMapper->extractPosterImageUrl($item);
                $existing = Event::query()->where('external_tm_id', $extEventId)->first();
                if ($existing !== null) {
                    if (! $existing->tm_sync_paused && $posterUrl !== null && $existing->image_url !== $posterUrl) {
                        $existing->image_url = $posterUrl;
                        $existing->save();
                    }

                    continue;
                }

                $fixedPrice = (float) Config::get('services.order.fixed_event_price_eur', 20.0);

                $row = new Event;
                $row->external_tm_id = $extEventId;
                $row->name = $name;
                $row->hold_ttl_seconds = 240;
                $row->venue_id = $venue->id;
                $row->starts_at = $startsAt;
                $row->hidden_at = null;
                $row->category = $category;
                $row->tm_sync_paused = false;
                $row->tm_url = $this->eventMapper->extractTmUrl($item);
                $row->price = $fixedPrice;
                $row->tm_category = $tmCategory;
                $row->is_large_event = true;
                $row->image_url = $posterUrl;
                $row->save();
                $inserted++;
            }

            if ($payload['more'] !== true) {
                break;
            }

            $page++;
        }

        $summary = [
            'inserted' => $inserted,
            'skipped_no_venue' => $skippedNoVenue,
            'skipped_existing' => $skippedExisting,
            'pages_fetched' => $pagesFetched,
            'errors' => $errors,
        ];

        Log::info('ticketmaster_event_import', $summary);

        return $summary;
    }

    /**
     * A. Carrega detall Discovery per ID TM.
     * B. Venue + dades d’esdeveniment (sense filtre de capacitat mínima).
     * C. Insert o actualització d’imatge si ja existeix.
     *
     * @return array{ok: bool, message?: string, http_status?: int, event_id?: int, inserted?: bool}
     */
    public function importSingleFromDiscoveryByExternalId(string $externalId): array
    {
        $item = $this->discoveryClient->fetchEventDetailById($externalId);
        if ($item === null) {
            return [
                'ok' => false,
                'message' => 'No s’ha pogut carregar l’esdeveniment des de Ticketmaster.',
                'http_status' => 503,
            ];
        }

        $venuePayload = $this->eventMapper->extractFirstVenue($item);
        if ($venuePayload === null) {
            return [
                'ok' => false,
                'message' => 'Sense recinte associat a Ticketmaster.',
                'http_status' => 422,
            ];
        }

        $venue = $this->venueUpsert->upsertVenueFromPayload($venuePayload);
        if ($venue === null) {
            return [
                'ok' => false,
                'message' => 'No s’ha pogut persistir el recinte.',
                'http_status' => 422,
            ];
        }

        $name = $this->eventMapper->extractEventName($item);
        $startsAt = $this->eventMapper->extractStartsAt($item);
        $category = $this->eventMapper->extractCategory($item);

        if ($startsAt === null) {
            return [
                'ok' => false,
                'message' => 'Sense data d’inici vàlida a Ticketmaster.',
                'http_status' => 422,
            ];
        }

        $tmCategory = $this->eventMapper->extractAndMapCategory($item);
        if ($tmCategory === null) {
            return [
                'ok' => false,
                'message' => 'Categoria TM no permesa per al catàleg.',
                'http_status' => 422,
            ];
        }

        $posterUrl = $this->eventMapper->extractPosterImageUrl($item);
        $existing = Event::query()->where('external_tm_id', $externalId)->first();
        if ($existing !== null) {
            if (! $existing->tm_sync_paused && $posterUrl !== null && $existing->image_url !== $posterUrl) {
                $existing->image_url = $posterUrl;
                $existing->save();
            }

            return [
                'ok' => true,
                'event_id' => (int) $existing->id,
                'inserted' => false,
            ];
        }

        $fixedPrice = (float) Config::get('services.order.fixed_event_price_eur', 20.0);

        $row = new Event;
        $row->external_tm_id = $externalId;
        $row->name = $name;
        $row->hold_ttl_seconds = 240;
        $row->venue_id = $venue->id;
        $row->starts_at = $startsAt;
        $row->hidden_at = null;
        $row->category = $category;
        $row->tm_sync_paused = false;
        $row->tm_url = $this->eventMapper->extractTmUrl($item);
        $row->price = $fixedPrice;
        $row->tm_category = $tmCategory;
        $row->is_large_event = $this->eventMapper->isLargeEvent($item);
        $row->image_url = $posterUrl;
        $row->save();

        return [
            'ok' => true,
            'event_id' => (int) $row->id,
            'inserted' => true,
        ];
    }
}
