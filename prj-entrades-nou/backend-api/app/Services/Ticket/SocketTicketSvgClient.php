<?php

namespace App\Services\Ticket;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Demana al socket-server la generació del SVG del QR (HTTP intern, T026).
 */
class SocketTicketSvgClient
{
    /**
     * @return non-empty-string|null SVG o null si no hi ha URL interna o falla la crida
     */
    public function svgForQrPayload(string $qrText): ?string
    {
        if ($qrText === '') {
            return null;
        }

        $base = Config::get('services.socket.internal_url');
        if ($base === null || $base === '') {
            return null;
        }

        $url = rtrim((string) $base, '/').'/internal/qr-svg';
        $secret = Config::get('services.socket.internal_secret');

        try {
            $headers = [
                'Accept' => 'image/svg+xml, application/json',
            ];
            if ($secret !== null && $secret !== '') {
                $headers['X-Internal-Secret'] = $secret;
            }

            $response = Http::timeout(8)->withHeaders($headers)->post($url, [
                'text' => $qrText,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '') {
                return null;
            }

            return $body;
        } catch (\Throwable) {
            return null;
        }
    }
}
