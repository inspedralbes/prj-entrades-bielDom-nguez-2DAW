<?php

namespace App\Services\Ticketmaster\Mapping;

//================================ NAMESPACES / IMPORTS ============

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Transformació de payloads Ticketmaster Discovery → camps de domini (sense Eloquent).
 */
class TicketmasterDiscoveryEventMapper
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    public function extractFirstVenue(array $item): ?array
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
     * @param  array<string, mixed>  $item
     */
    public function extractEventName(array $item): string
    {
        if (isset($item['name']) && is_string($item['name']) && $item['name'] !== '') {
            return $item['name'];
        }

        return 'Esdeveniment sense nom';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function extractStartsAt(array $item): ?Carbon
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

    /**
     * @param  array<string, mixed>  $item
     */
    public function extractCategory(array $item): ?string
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
     * Extreu i mapeja la categoria TM a la categoria interna simplificada.
     * Retorna null si la categoria no està a la llista permesa (exclou museus).
     *
     * @param  array<string, mixed>  $item
     */
    public function extractAndMapCategory(array $item): ?string
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
                    $genreLower = strtolower($genreName);
                    if ($genreLower === 'museum' || str_contains($genreLower, 'museum')) {
                        return null;
                    }
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
     * Comprova si la capacitat de l’esdeveniment és prou gran (no és un esdeveniment petit).
     *
     * @param  array<string, mixed>  $item
     */
    public function isLargeEvent(array $item): bool
    {
        $minCapacity = (int) Config::get('services.ticketmaster.min_capacity', 500);

        if (isset($item['_embedded']['venues']) && is_array($item['_embedded']['venues'])) {
            $venues = $item['_embedded']['venues'];
            if (count($venues) > 0) {
                $venue = $venues[0];
                if (is_array($venue) && isset($venue['capacity'])) {
                    $capacity = (int) $venue['capacity'];

                    return $capacity >= $minCapacity;
                }
            }
        }

        if (! isset($item['info']) && ! isset($item['pleaseNote'])) {
            return true;
        }

        return true;
    }

    /**
     * Millor URL d’imatge pòster des de Discovery API (`images[]`).
     *
     * @param  array<string, mixed>  $item
     */
    public function extractPosterImageUrl(array $item): ?string
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
     * @param  array<string, mixed>  $item
     */
    public function extractTmUrl(array $item): ?string
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
     * @param  array<string, mixed>  $item
     */
    public function extractPrice(array $item): ?float
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

        if ($min > 0) {
            return $min;
        }

        return null;
    }
}
