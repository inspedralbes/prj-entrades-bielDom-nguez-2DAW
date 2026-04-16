<?php

namespace App\Services\Seatmap;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Seatmap\CinemaVenueLayout;
use Illuminate\Support\Facades\Redis;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Bloquejos temporals de seients (Redis) vs vendes a events.seat_layout (JSONB).
 */
class EventSeatHoldService
{
    public function __construct(
        private readonly EventSeatRedisKeyFactory $redisKeys,
        private readonly EventSeatStatusEmitter $statusEmitter,
        private readonly EventSeatHoldRedisScanner $holdScanner,
    ) {}

    public function redisSeatKey(int|string $eventId, string $seatId): string
    {
        return $this->redisKeys->redisSeatKey($eventId, $seatId);
    }

    /**
     * Índex per alliberar ràpid en desconnexió: SET de seat_id.
     */
    public function userEventHoldsKey(int $userId, int|string $eventId): string
    {
        return $this->redisKeys->userEventHoldsKey($userId, $eventId);
    }

    /**
     * @return array{ok: true}|array{ok: false, reason: string, message?: string}
     */
    public function holdSeat(Event $event, string $seatId, int $userId): array
    {
        if (! CinemaVenueLayout::isValidSeatId($seatId)) {
            return ['ok' => false, 'reason' => 'invalid_seat', 'message' => 'Seient no vàlid per al mapa'];
        }

        $layout = $event->seat_layout;
        if (! is_array($layout)) {
            $layout = [];
        }

        if ($this->isSeatSoldInLayout($layout, $seatId)) {
            return ['ok' => false, 'reason' => 'sold', 'message' => 'Aquest seient ja està venut'];
        }

        $key = $this->redisKeys->redisSeatKey($event->id, $seatId);
        $conn = Redis::connection();

        $existing = $conn->get($key);
        if ($existing !== null && $existing !== false && $existing !== '') {
            $holder = (string) $existing;
            if ($holder !== (string) $userId) {
                return ['ok' => false, 'reason' => 'held_by_other', 'message' => 'Un altre usuari ha reservat aquest seient'];
            }

            $conn->expire($key, 120);

            return ['ok' => true];
        }

        $conn->setex($key, 120, (string) $userId);

        $indexKey = $this->redisKeys->userEventHoldsKey($userId, $event->id);
        $conn->sadd($indexKey, $seatId);
        $conn->expire($indexKey, 180);

        $this->statusEmitter->emit($event->id, $seatId, 'held', $userId);

        return ['ok' => true];
    }

    /**
     * Allibera tots els holds Redis d’un usuari (p. ex. desconnexió socket).
     *
     * @return list<string> seat_id alliberats
     */
    public function releaseAllHoldsForUser(int $userId, ?int $eventIdFilter = null): array
    {
        $conn = Redis::connection();
        $released = [];

        if ($eventIdFilter !== null) {
            return $this->releaseHoldsForUserEvent($conn, $userId, $eventIdFilter);
        }

        $pattern = 'user:'.(string) $userId.':event:*:held_seats';
        $keys = $conn->keys($pattern);
        if (! is_array($keys)) {
            return [];
        }

        $n = count($keys);
        for ($i = 0; $i < $n; $i++) {
            $indexKey = $keys[$i];
            $seatIds = $conn->smembers($indexKey);
            if (! is_array($seatIds)) {
                continue;
            }
            $evId = $this->parseEventIdFromUserIndexKey($indexKey);
            if ($evId === null) {
                continue;
            }
            $m = count($seatIds);
            for ($j = 0; $j < $m; $j++) {
                $sid = (string) $seatIds[$j];
                $conn->del($this->redisKeys->redisSeatKey($evId, $sid));
                $released[] = $sid;
                $this->statusEmitter->emit($evId, $sid, 'available', null);
            }
            $conn->del($indexKey);
        }

        return $released;
    }

    /**
     * Després de marcar seients com venuts a PostgreSQL: neteja Redis i notifica tots els clients (estat sold).
     *
     * @param  list<string>  $seatKeys
     */
    public function finalizeCinemaSeatsAsSold(int|string $eventId, int $userId, array $seatKeys): void
    {
        $conn = Redis::connection();
        $indexKey = $this->redisKeys->userEventHoldsKey($userId, (int) $eventId);
        $nk = count($seatKeys);
        for ($i = 0; $i < $nk; $i++) {
            $sk = (string) $seatKeys[$i];
            $conn->del($this->redisKeys->redisSeatKey($eventId, $sk));
            $conn->srem($indexKey, $sk);
            $this->statusEmitter->emit($eventId, $sk, 'sold', null);
        }
    }

    /**
     * L’usuari deselecciona un seient al mapa: allibera Redis i notifica available.
     */
    public function releaseUserSeatHold(Event $event, string $seatId, int $userId): bool
    {
        $conn = Redis::connection();
        $key = $this->redisKeys->redisSeatKey($event->id, $seatId);
        $val = $conn->get($key);
        if ($val === null || $val === false || (string) $val !== (string) $userId) {
            return false;
        }

        $conn->del($key);
        $indexKey = $this->redisKeys->userEventHoldsKey($userId, (int) $event->id);
        $conn->srem($indexKey, $seatId);
        $this->statusEmitter->emit($event->id, $seatId, 'available', null);

        return true;
    }

    /**
     * @return array<string, string> seat_id => user_id (string)
     */
    public function getHoldsForEvent(int|string $eventId): array
    {
        return $this->holdScanner->getHoldsForEvent($eventId);
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    private function isSeatSoldInLayout(array $layout, string $seatId): bool
    {
        if (! array_key_exists($seatId, $layout)) {
            return false;
        }

        $val = $layout[$seatId];
        if ($val === true || $val === 1 || $val === '1') {
            return true;
        }

        if (is_string($val) && $val !== '' && $val !== '0') {
            return true;
        }

        return false;
    }

    /**
     * @param  \Illuminate\Redis\Connections\Connection  $conn
     * @return list<string>
     */
    private function releaseHoldsForUserEvent($conn, int $userId, int $eventId): array
    {
        $indexKey = $this->redisKeys->userEventHoldsKey($userId, $eventId);
        $seatIds = $conn->smembers($indexKey);
        $released = [];
        if (! is_array($seatIds)) {
            return $released;
        }

        $m = count($seatIds);
        for ($j = 0; $j < $m; $j++) {
            $sid = (string) $seatIds[$j];
            $conn->del($this->redisKeys->redisSeatKey($eventId, $sid));
            $released[] = $sid;
            $this->statusEmitter->emit($eventId, $sid, 'available', null);
        }
        $conn->del($indexKey);

        return $released;
    }

    private function parseEventIdFromUserIndexKey(string $key): ?int
    {
        if (preg_match('/user:\d+:event:(\d+):held_seats$/', $key, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
