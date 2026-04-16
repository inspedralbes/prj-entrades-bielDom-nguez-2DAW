<?php

namespace App\Services\Seatmap;

//================================ NAMESPACES / IMPORTS ============

use App\Services\Socket\InternalSocketNotifier;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Emissió d’esdeveniments de canvi d’estat de seient (cinema) cap al socket intern.
 */
class EventSeatStatusEmitter
{
    public function __construct(
        private readonly InternalSocketNotifier $socketNotifier,
    ) {}

    /**
     * A. Construeix payload estable per al client.
     * B. Publica a la sala de l’esdeveniment.
     */
    public function emit(int|string $eventId, string $seatId, string $status, ?int $holderUserId): void
    {
        $payload = [
            'eventId' => (string) $eventId,
            'seatId' => $seatId,
            'status' => $status,
            'userId' => $holderUserId !== null ? (string) $holderUserId : null,
        ];

        $this->socketNotifier->emitToEventRoom((string) $eventId, 'SeatStatusUpdated', $payload);
    }
}
