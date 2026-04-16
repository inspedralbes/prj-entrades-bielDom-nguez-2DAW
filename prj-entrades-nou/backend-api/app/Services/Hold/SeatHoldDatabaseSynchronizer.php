<?php

namespace App\Services\Hold;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Seat;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Coherència de files `seats` amb l’estat de holds (PostgreSQL).
 */
class SeatHoldDatabaseSynchronizer
{
    /**
     * Allibera totes les files de seients associades a un hold UUID.
     */
    public function releaseSeatsByHoldUuid(string $holdUuid): void
    {
        Seat::query()
            ->where('current_hold_id', $holdUuid)
            ->update([
                'status' => 'available',
                'current_hold_id' => null,
                'held_until' => null,
            ]);
    }

    /**
     * Neteja bloquejos caducats (cron / abans de crear hold).
     */
    public function releaseExpiredSeatLocks(): void
    {
        Seat::query()
            ->whereNotNull('held_until')
            ->where('held_until', '<', now())
            ->update([
                'status' => 'available',
                'current_hold_id' => null,
                'held_until' => null,
            ]);
    }
}
