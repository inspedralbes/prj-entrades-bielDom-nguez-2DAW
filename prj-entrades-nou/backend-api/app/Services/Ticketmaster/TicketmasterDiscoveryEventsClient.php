<?php

namespace App\Services\Ticketmaster;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP Discovery API v2: llista d'esdeveniments per sincronitzar la taula `events`.
 */
class TicketmasterDiscoveryEventsClient
{
    /**
     * @return array{events: array<int, array<string, mixed>>, page: int, total_pages: int, more: bool}|null
     */
    public function fetchEventsPage(int $pageZeroBased, int $size, string $countryCode): ?array
    {
        $disabled = Config::get('services.ticketmaster.disabled');
        if ($disabled === true) {
            return null;
        }

        $key = Config::get('services.ticketmaster.key');
        if ($key === null || $key === '') {
            return null;
        }

        $base = (string) Config::get('services.ticketmaster.base_url');
        $url = rtrim($base, '/').'/events.json';

        $response = null;
        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->get($url, [
                    'apikey' => $key,
                    'countryCode' => $countryCode,
                    'size' => $size,
                    'page' => $pageZeroBased,
                    'sort' => 'date,asc',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if ($response === null || !$response->successful()) {
            return null;
        }

        $data = $response->json();
        if (!is_array($data)) {
            return null;
        }

        $events = [];
        if (isset($data['_embedded']['events']) && is_array($data['_embedded']['events'])) {
            $events = $data['_embedded']['events'];
        }

        $totalPages = 0;
        if (isset($data['page']) && is_array($data['page'])) {
            $p = $data['page'];
            if (isset($p['totalPages']) && is_numeric($p['totalPages'])) {
                $totalPages = (int) $p['totalPages'];
            }
        }

        $more = false;
        if ($totalPages > 0) {
            $more = ($pageZeroBased + 1) < $totalPages;
        }

        return [
            'events' => $events,
            'page' => $pageZeroBased,
            'total_pages' => $totalPages,
            'more' => $more,
        ];
    }

    /**
     * Cerca per paraula clau (Discovery API v2).
     *
     * @return array{events: array<int, array<string, mixed>>, page: int, total_pages: int, more: bool}|null
     */
    public function searchEventsPage(string $keyword, int $pageZeroBased, int $size, string $countryCode): ?array
    {
        $disabled = Config::get('services.ticketmaster.disabled');
        if ($disabled === true) {
            return null;
        }

        $key = Config::get('services.ticketmaster.key');
        if ($key === null || $key === '') {
            return null;
        }

        $base = (string) Config::get('services.ticketmaster.base_url');
        $url = rtrim($base, '/').'/events.json';

        $response = null;
        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->get($url, [
                    'apikey' => $key,
                    'countryCode' => $countryCode,
                    'keyword' => $keyword,
                    'size' => $size,
                    'page' => $pageZeroBased,
                    'sort' => 'relevance,asc',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if ($response === null || !$response->successful()) {
            return null;
        }

        $data = $response->json();
        if (!is_array($data)) {
            return null;
        }

        $events = [];
        if (isset($data['_embedded']['events']) && is_array($data['_embedded']['events'])) {
            $events = $data['_embedded']['events'];
        }

        $totalPages = 0;
        if (isset($data['page']) && is_array($data['page'])) {
            $p = $data['page'];
            if (isset($p['totalPages']) && is_numeric($p['totalPages'])) {
                $totalPages = (int) $p['totalPages'];
            }
        }

        $more = false;
        if ($totalPages > 0) {
            $more = ($pageZeroBased + 1) < $totalPages;
        }

        return [
            'events' => $events,
            'page' => $pageZeroBased,
            'total_pages' => $totalPages,
            'more' => $more,
        ];
    }

    /**
     * Detall d’un esdeveniment TM per ID (p. ex. G5diZfknQB6HVh).
     *
     * @return array<string, mixed>|null
     */
    public function fetchEventDetailById(string $externalId): ?array
    {
        $disabled = Config::get('services.ticketmaster.disabled');
        if ($disabled === true) {
            return null;
        }

        $key = Config::get('services.ticketmaster.key');
        if ($key === null || $key === '') {
            return null;
        }

        $base = (string) Config::get('services.ticketmaster.base_url');
        $url = rtrim($base, '/').'/events/'.$externalId.'.json';

        $response = null;
        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->get($url, [
                    'apikey' => $key,
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if ($response === null || !$response->successful()) {
            return null;
        }

        $data = $response->json();
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }
}
