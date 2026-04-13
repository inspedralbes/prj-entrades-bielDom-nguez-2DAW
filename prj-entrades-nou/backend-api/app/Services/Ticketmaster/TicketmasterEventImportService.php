<?php

namespace App\Services\Ticketmaster;

use App\Models\Event;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa o actualitza `venues` i `events` des de Ticketmaster Discovery (id extern `external_tm_id`).
 * Si `events.tm_sync_paused` és cert, no s'actualitza la fila (edicions locals / administrador).
 */
class TicketmasterEventImportService
{
    public function __construct (
        private readonly TicketmasterDiscoveryEventsClient $discoveryClient,
    ) {}

    /**
     * @return array{
     *   inserted: int,
     *   skipped_no_venue: int,
     *   skipped_existing: int,
     *   pages_fetched: int,
     *   errors: array<int, string>
     * }
     */
    public function sync (?int $maxPagesOverride = null): array
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

                $venuePayload = $this->extractFirstVenue($item);
                if ($venuePayload === null) {
                    $skippedNoVenue++;

                    continue;
                }

                $venue = $this->upsertVenueFromPayload($venuePayload);
                if ($venue === null) {
                    $skippedNoVenue++;

                    continue;
                }

                $name = $this->extractEventName($item);
                $startsAt = $this->extractStartsAt($item);
                $category = $this->extractCategory($item);

                if ($startsAt === null) {
                    $errors[] = 'Sense data d\'inici per esdeveniment '.$extEventId;

                    continue;
                }

                $tmCategory = $this->extractAndMapCategory($item);
                if ($tmCategory === null) {
                    $skippedExisting++;

                    continue;
                }

                if (! $this->isLargeEvent($item)) {
                    $skippedExisting++;

                    continue;
                }

                $posterUrl = $this->extractPosterImageUrl($item);
                $existing = Event::query()->where('external_tm_id', $extEventId)->first();
                if ($existing !== null) {
                    if (! $existing->tm_sync_paused && $posterUrl !== null && $existing->image_url !== $posterUrl) {
                        $existing->image_url = $posterUrl;
                        $existing->save();
                    }

                    continue;
                }

                $fixedPrice = (float) Config::get('services.order.fixed_event_price_eur', 20.0);

                $row = new Event();
                $row->external_tm_id = $extEventId;
                $row->name = $name;
                $row->hold_ttl_seconds = 240;
                $row->venue_id = $venue->id;
                $row->starts_at = $startsAt;
                $row->hidden_at = null;
                $row->category = $category;
                $row->tm_sync_paused = false;
                $row->tm_url = $this->extractTmUrl($item);
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
     * @param  array<string, mixed>  $item
     */
    private function extractFirstVenue (array $item): ?array
    {
        if (! isset($item['_embedded']['venues'])) {
            return null;
        }
        $venues = $item['_embedded']['venues'];
        if (! is_array($venues)) {
            return null;
        }
        if (count($venues) === 0) {
            return null;
        }
        $first = $venues[0];
        if (! is_array($first)) {
            return null;
        }

        return $first;
    }

    /**
     * @param  array<string, mixed>  $v0
     */
    private function upsertVenueFromPayload (array $v0): ?Venue
    {
        $tmVenueId = null;
        if (isset($v0['id']) && is_string($v0['id'])) {
            $tmVenueId = $v0['id'];
        }
        if ($tmVenueId === null || $tmVenueId === '') {
            return null;
        }

        $venueName = 'Sense nom';
        if (isset($v0['name']) && is_string($v0['name']) && $v0['name'] !== '') {
            $venueName = $v0['name'];
        }

        $address = null;
        $city = null;

        if (isset($v0['address']) && is_array($v0['address']) && isset($v0['address']['line1'])) {
            $address = (string) $v0['address']['line1'];
        }

        if (isset($v0['city']) && is_array($v0['city']) && isset($v0['city']['name'])) {
            $city = (string) $v0['city']['name'];
        }

        $venue = Venue::query()->where('external_tm_id', $tmVenueId)->first();
        if ($venue === null) {
            $venue = new Venue();
            $venue->external_tm_id = $tmVenueId;
            $venue->name = $venueName;
            $venue->address = $address;
            $venue->city = $city;
            $venue->save();
        } else {
            $venue->name = $venueName;
            if ($address !== null) {
                $venue->address = $address;
            }
            if ($city !== null) {
                $venue->city = $city;
            }
            $venue->save();
        }

        $lat = null;
        $lng = null;
        if (isset($v0['location']) && is_array($v0['location'])) {
            $loc = $v0['location'];
            if (isset($loc['latitude'])) {
                $lat = (float) $loc['latitude'];
            }
            if (isset($loc['longitude'])) {
                $lng = (float) $loc['longitude'];
            }
        }

        $this->applyVenueLocation($venue, $lat, $lng);

        return $venue;
    }

    private function applyVenueLocation (Venue $venue, ?float $lat, ?float $lng): void
    {
        if ($lat === null || $lng === null) {
            return;
        }
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::statement(
            'UPDATE venues SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = ? WHERE id = ?',
            [$lng, $lat, now(), $venue->id]
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractEventName (array $item): string
    {
        if (isset($item['name']) && is_string($item['name']) && $item['name'] !== '') {
            return $item['name'];
        }

        return 'Esdeveniment sense nom';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractStartsAt (array $item): ?Carbon
    {
        if (! isset($item['dates']) || ! is_array($item['dates'])) {
            return null;
        }
        $dates = $item['dates'];
        if (! isset($dates['start']) || ! is_array($dates['start'])) {
            return null;
        }
        $start = $dates['start'];

        $raw = null;
        if (isset($start['dateTime']) && is_string($start['dateTime'])) {
            $raw = $start['dateTime'];
        }
        if ($raw === null && isset($start['localDate']) && is_string($start['localDate'])) {
            $raw = $start['localDate'];
            if (isset($start['localTime']) && is_string($start['localTime'])) {
                $raw = $raw.'T'.$start['localTime'];
            }
        }
        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractCategory (array $item): ?string
    {
        if (! isset($item['classifications']) || ! is_array($item['classifications'])) {
            return null;
        }
        $list = $item['classifications'];
        foreach ($list as $c) {
            if (! is_array($c)) {
                continue;
            }
            if (! isset($c['segment']) || ! is_array($c['segment'])) {
                continue;
            }
            $seg = $c['segment'];
            if (isset($seg['name']) && is_string($seg['name']) && $seg['name'] !== '') {
                return $seg['name'];
            }
        }

        return null;
    }

    /**
     * Extract and map TM category to internal simplified category.
     * Returns null if category is not in allowed list (excludes museums).
     *
     * @param  array<string, mixed>  $item
     */
    private function extractAndMapCategory (array $item): ?string
    {
        if (! isset($item['classifications']) || ! is_array($item['classifications'])) {
            return null;
        }

        $allowedCategories = (array) Config::get('services.ticketmaster.allowed_categories', [
            'Party', 'DJ', 'Social', 'Comedy', 'Film', 'Theatre', 'Art', 'Sport', 'Talk', 'Concert',
        ]);

        $categoryMapping = [
            'Music' => 'Concert',
            'Arts' => 'Art',
            'Sports' => 'Sport',
            'Miscellaneous' => 'Party',
        ];

        $list = $item['classifications'];
        foreach ($list as $c) {
            if (! is_array($c)) {
                continue;
            }

            if (isset($c['genre']) && is_array($c['genre'])) {
                $genre = $c['genre'];
                if (isset($genre['name']) && is_string($genre['name'])) {
                    $genreName = $genre['name'];
                    if (in_array($genreName, ['DJ', 'Rave', 'Electronic'])) {
                        return 'DJ';
                    }
                    if (in_array($genreName, ['Stand-up', 'Improv', 'Comedy'])) {
                        return 'Comedy';
                    }
                }
            }

            if (! isset($c['segment']) || ! is_array($c['segment'])) {
                continue;
            }
            $seg = $c['segment'];
            if (! isset($seg['name']) || ! is_string($seg['name'])) {
                continue;
            }

            $segmentName = $seg['name'];

            if (stripos($segmentName, 'Museum') !== false) {
                return null;
            }

            if (isset($categoryMapping[$segmentName])) {
                return $categoryMapping[$segmentName];
            }

            if (in_array($segmentName, $allowedCategories, true)) {
                return $segmentName;
            }
        }

        return null;
    }

    /**
     * Check if event capacity is large enough (not a small event).
     *
     * @param  array<string, mixed>  $item
     */
    private function isLargeEvent (array $item): bool
    {
        $minCapacity = (int) Config::get('services.ticketmaster.min_capacity', 500);

        if (! isset($item['info']) && ! isset($item['pleaseNote'])) {
            return true;
        }

        if (! isset($item['_embedded']['venues']) || ! is_array($item['_embedded']['venues'])) {
            return true;
        }

        $venues = $item['_embedded']['venues'];
        if (count($venues) === 0) {
            return true;
        }

        $venue = $venues[0];
        if (! is_array($venue) || ! isset($venue['capacity'])) {
            return true;
        }

        $capacity = (int) $venue['capacity'];

        return $capacity >= $minCapacity;
    }

    /**
     * Millor URL d’imatge pòster des de Discovery API (`images[]`: url pública HTTPS, sense clau API).
     * Es tria la imatge amb més superfície (width×height) per qualitat en UI.
     *
     * @param  array<string, mixed>  $item
     */
    private function extractPosterImageUrl (array $item): ?string
    {
        if (! isset($item['images']) || ! is_array($item['images']) || $item['images'] === []) {
            return null;
        }

        $bestUrl = null;
        $bestArea = -1;

        foreach ($item['images'] as $img) {
            if (! is_array($img) || ! isset($img['url']) || ! is_string($img['url'])) {
                continue;
            }
            $url = trim($img['url']);
            if ($url === '' || ! str_starts_with($url, 'http')) {
                continue;
            }

            $w = isset($img['width']) ? (int) $img['width'] : 0;
            $h = isset($img['height']) ? (int) $img['height'] : 0;
            $area = $w * $h;
            if ($bestUrl === null || $area > $bestArea) {
                $bestArea = $area;
                $bestUrl = $url;
            }
        }

        return $bestUrl;
    }

    /**
     * Extract Ticketmaster URL from event.
     *
     * @param  array<string, mixed>  $item
     */
    private function extractTmUrl (array $item): ?string
    {
        if (isset($item['url']) && is_string($item['url'])) {
            return $item['url'];
        }

        if (isset($item['externalLinks']) && is_array($item['externalLinks'])) {
            if (isset($item['externalLinks']['ticketmaster']) && is_array($item['externalLinks']['ticketmaster'])) {
                $links = $item['externalLinks']['ticketmaster'];
                if (count($links) > 0 && isset($links[0]['url'])) {
                    return $links[0]['url'];
                }
            }
        }

        return null;
    }

    /**
     * Extract price from Ticketmaster event.
     *
     * @param  array<string, mixed>  $item
     */
    private function extractPrice (array $item): ?float
    {
        if (! isset($item['priceRanges']) || ! is_array($item['priceRanges'])) {
            return null;
        }

        $ranges = $item['priceRanges'];
        if (count($ranges) === 0) {
            return null;
        }

        $first = $ranges[0];
        if (! is_array($first) || ! isset($first['min'])) {
            return null;
        }

        $min = (float) $first['min'];

        return $min > 0 ? $min : null;
    }
}
