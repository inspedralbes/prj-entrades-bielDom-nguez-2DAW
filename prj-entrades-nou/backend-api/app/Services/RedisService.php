<?php

namespace App\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Accés centralitzat a Redis (holds, pub/sub futur).
 */
class RedisService
{
    public function connection(?string $name = null): Connection
    {
        return Redis::connection($name);
    }

    /**
     * Patró de clau hold segons spec: hold:{eventId}:{holdUuid}
     */
    public function holdKey(string $eventId, string $holdUuid): string
    {
        return 'hold:'.$eventId.':'.$holdUuid;
    }
}
