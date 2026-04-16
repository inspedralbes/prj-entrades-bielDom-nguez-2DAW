<?php

namespace App\Services\Socket;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Pont Laravel → socket-server (HTTP POST). Equivalent a pub/sub cap al Node sense dependre de Redis PUBLISH per aquest canal.
 */
class InternalSocketNotifier
{
    public function emitToAnonSession(string $anonymousSessionId, string $eventName, array $payload): void
    {
        $this->emit('anon:'.$anonymousSessionId, $eventName, $payload);
    }

    public function emitToEventRoom(string $eventId, string $eventName, array $payload): void
    {
        $this->emit('event:'.$eventId, $eventName, $payload);
    }

    /**
     * Room {@code user:{id}} — mateix patró que el namespace privat del socket-server (T031).
     */
    public function emitToUser(int|string $userId, string $eventName, array $payload): void
    {
        $this->emit('user:'.(string) $userId, $eventName, $payload);
    }

    /**
     * Esborrany T035: notificació mètriques cap a clients que escoltin el room (p. ex. panell admin T052).
     */
    public function emitMetricsStub(array $payload): void
    {
        $this->emit('admin:dashboard', 'admin:metrics', $payload);
    }

    /**
     * Emit low stock warning for an event.
     */
    public function emitLowStock(int $eventId, int $remainingTickets): void
    {
        $this->emit('event:'.$eventId, 'event:low_stock', [
            'event_id' => $eventId,
            'remaining_tickets' => $remainingTickets,
            'threshold' => 10,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    //================================ LÒGICA PRIVADA ================

    /**
     * A. Llegeix URL i secret intern (`SOCKET_SERVER_INTERNAL_URL`, `SOCKET_INTERNAL_SECRET`).
     * B. POST JSON a `/internal/emit` (cos amb `room`, `event`, `payload`).
     * C. Registre d’errors HTTP o excepcions sense trencar la transacció Laravel.
     */
    private function emit(string $room, string $eventName, array $payload): void
    {
        $base = Config::get('services.socket.internal_url');
        if ($base === null || $base === '') {
            Log::warning('socket.internal_emit_skipped_no_url', [
                'room' => $room,
                'event' => $eventName,
                'hint' => 'Defineix SOCKET_SERVER_INTERNAL_URL (dins Docker: http://socket-server:3001)',
            ]);

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

            // Cal enviar JSON: el socket-server fa JSON.parse del cos; sense asJson() Laravel envia form-urlencoded i /internal/emit retorna 400.
            $response = Http::timeout(3)->withHeaders($headers)->asJson()->post($url, [
                'room' => $room,
                'event' => $eventName,
                'payload' => $payload,
            ]);

            if ($response->failed()) {
                Log::warning('socket.internal_emit_http_error', [
                    'room' => $room,
                    'event' => $eventName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('socket.internal_emit_failed', [
                'room' => $room,
                'event' => $eventName,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
