<?php

namespace App\Services\Seatmap;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Support\Facades\Redis;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Lectura i interpretació de claus Redis de holds de mapa cinema (`event:{id}:seat:*`).
 */
class EventSeatHoldRedisScanner
{
    public function __construct(
        private readonly EventSeatRedisKeyFactory $redisKeys,
    ) {}

    /**
     * @return array<string, string> seat_id => user_id (string)
     */
    public function getHoldsForEvent(int|string $eventId): array
    {
        $out = [];

        try {
            $conn = Redis::connection();
            $pattern = 'event:'.(string) $eventId.':seat:*';
            $keys = $conn->keys($pattern);
            if (! is_array($keys)) {
                return $out;
            }

            $n = count($keys);
            for ($i = 0; $i < $n; $i++) {
                $k = $keys[$i];
                $seatId = $this->parseSeatIdFromRedisKey((string) $k);
                if ($seatId === null) {
                    continue;
                }
                $logicalKey = $this->redisKeys->redisSeatKey($eventId, $seatId);
                $val = $conn->get($logicalKey);
                if ($val === null || $val === false || $val === '') {
                    continue;
                }
                $out[$seatId] = (string) $val;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $out;
    }

    //================================ LÒGICA PRIVADA ================

    private function parseSeatIdFromRedisKey(string $key): ?string
    {
        if (preg_match('/event:\d+:seat:(.+)$/', $key, $m)) {
            return $m[1];
        }

        return null;
    }
}
