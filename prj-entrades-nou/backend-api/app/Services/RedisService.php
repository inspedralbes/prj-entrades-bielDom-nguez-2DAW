<?php

namespace App\Services;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Façana d’accés a Redis (Agent Redis): holds, futur pub/sub; PostgreSQL continua sent la SoT.
 */
class RedisService
{
    /**
     * A. Resolució del nom de connexió (per defecte `default`).
     * B. Connexió via façana Laravel `Redis`.
     * C. Retorn del client per operacions (ZSET presència, claus de hold, etc.).
     */
    public function connection(?string $name = null): Connection
    {
        return Redis::connection($name);
    }

    /**
     * Patró de clau hold segons especificació: `hold:{eventId}:{holdUuid}`.
     */
    public function holdKey(string $eventId, string $holdUuid): string
    {
        return 'hold:'.$eventId.':'.$holdUuid;
    }
}
