<?php

namespace App\Services\Seatmap;

//================================ NAMESPACES / IMPORTS ==========

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Noms de claus Redis per a holds de mapa cinema (sense connexió I/O).
 */
class EventSeatRedisKeyFactory
{
    public function redisSeatKey(int|string $eventId, string $seatId): string
    {
        return 'event:'.(string) $eventId.':seat:'.$seatId;
    }

    /**
     * Índex per alliberar ràpid en desconnexió: SET de seat_id.
     */
    public function userEventHoldsKey(int $userId, int|string $eventId): string
    {
        return 'user:'.(string) $userId.':event:'.(string) $eventId.':held_seats';
    }
}
