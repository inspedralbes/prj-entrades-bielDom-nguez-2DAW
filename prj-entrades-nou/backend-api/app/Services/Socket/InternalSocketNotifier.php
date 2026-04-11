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

    /**
     * Room {@code user:{id}} — mateix patró que el namespace privat del socket-server (T031).
     */
    public function emitToUser (int|string $userId, string $eventName, array $payload): void
    {
        $this->emit('user:'.(string) $userId, $eventName, $payload);
    }

    /**
     * Esborrany T035: notificació mètriques cap a clients que escoltin el room (p. ex. panell admin T052).
     */
    public function emitMetricsStub (array $payload): void
    {
        $this->emit('admin:dashboard', 'admin:metrics', $payload);
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
