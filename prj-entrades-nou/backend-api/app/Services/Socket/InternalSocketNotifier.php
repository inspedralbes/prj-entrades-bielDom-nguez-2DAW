<?php

namespace App\Services\Socket;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Notifica el socket-server (HTTP intern) per retransmetre esdeveniments a rooms (T018).
 */
class InternalSocketNotifier
{
    public function emitToAnonSession (string $anonymousSessionId, string $eventName, array $payload): void
    {
        $this->emit('anon:'.$anonymousSessionId, $eventName, $payload);
    }

    public function emitToEventRoom (string $eventId, string $eventName, array $payload): void
    {
        $this->emit('event:'.$eventId, $eventName, $payload);
    }

    private function emit (string $room, string $eventName, array $payload): void
    {
        $base = Config::get('services.socket.internal_url');
        if ($base === null || $base === '') {
            return;
        }

        $secret = Config::get('services.socket.internal_secret');

        $url = rtrim((string) $base, '/').'/internal/emit';

        try {
            $headers = [
                'Accept' => 'application/json',
            ];
            if ($secret !== null && $secret !== '') {
                $headers['X-Internal-Secret'] = $secret;
            }

            Http::timeout(3)->withHeaders($headers)->post($url, [
                'room' => $room,
                'event' => $eventName,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            // No bloquejar el flux de negoci si el worker Socket no està actiu.
        }
    }
}
