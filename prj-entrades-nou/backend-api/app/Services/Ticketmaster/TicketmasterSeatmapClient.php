<?php

namespace App\Services\Ticketmaster;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP cap a Discovery API (esdeveniment) per extreure URL d’imatge del mapa si Ticketmaster la proporciona.
 */
class TicketmasterSeatmapClient
{
    /**
     * @return array{snapshotImageUrl: string, zones: array<int, array{id: string, label: string}>}|null
     */
    public function fetch(?string $externalTmId): ?array
    {
        if ($externalTmId === null || $externalTmId === '') {
            return null;
        }

        $disabled = Config::get('services.ticketmaster.disabled');
        if ($disabled === true) {
            return null;
        }

        $key = Config::get('services.ticketmaster.key');
        if ($key === null || $key === '') {
            return null;
        }

        $base = (string) Config::get('services.ticketmaster.base_url');
        $url = rtrim($base, '/').'/events/'.rawurlencode($externalTmId).'.json';

        $response = null;
        try {
            $response = Http::timeout(12)
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

        $snapshot = null;
        if (isset($data['seatmap']) && is_array($data['seatmap'])) {
            $seatmap = $data['seatmap'];
            if (isset($seatmap['staticUrl']) && is_string($seatmap['staticUrl'])) {
                $snapshot = $seatmap['staticUrl'];
            }
        }

        if ($snapshot === null || $snapshot === '') {
            return null;
        }

        $zones = [];

        return [
            'snapshotImageUrl' => $snapshot,
            'zones' => $zones,
        ];
    }
}
