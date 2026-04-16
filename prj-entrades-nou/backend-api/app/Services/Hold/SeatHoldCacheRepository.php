<?php

namespace App\Services\Hold;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Support\Facades\Cache;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Emmagatzematge del payload de hold (cache Laravel; sovint Redis en producció).
 */
class SeatHoldCacheRepository
{
    public function cacheKeyForHold(string $holdUuid): string
    {
        return 'hold:byid:'.$holdUuid;
    }

    public function getRaw(string $holdUuid): mixed
    {
        return Cache::get($this->cacheKeyForHold($holdUuid));
    }

    public function putRaw(string $holdUuid, string $payloadJson, int $ttlSeconds): void
    {
        Cache::put($this->cacheKeyForHold($holdUuid), $payloadJson, $ttlSeconds);
    }

    public function forget(string $holdUuid): void
    {
        Cache::forget($this->cacheKeyForHold($holdUuid));
    }
}
