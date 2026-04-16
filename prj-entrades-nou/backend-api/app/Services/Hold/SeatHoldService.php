<?php

namespace App\Services\Hold;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\Seat;
use App\Services\Socket\InternalSocketNotifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class SeatHoldService
{
    public function __construct(
        private readonly InternalSocketNotifier $socketNotifier,
        private readonly SeatHoldCacheRepository $holdCache,
        private readonly SeatHoldDatabaseSynchronizer $seatDb,
    ) {}

    public function cacheKeyForHold(string $holdUuid): string
    {
        return $this->holdCache->cacheKeyForHold($holdUuid);
    }

    public function releaseExpiredSeatLocks(): void
    {
        $this->seatDb->releaseExpiredSeatLocks();
    }

    /**
     * @param  array<int, int>  $seatIds
     * @return array{ok: true, hold_id: string, expires_at: string, anonymous_session_id: string, seat_ids: array<int, int>}|array{ok: false, reason: string, seat_ids?: array<int, int>}
     */
    public function createHold(Event $event, array $seatIds, string $anonymousSessionId, ?int $userId): array
    {
        $this->releaseExpiredSeatLocks();

        $count = count($seatIds);
        if ($count < 1 || $count > 6) {
            return ['ok' => false, 'reason' => 'invalid_seat_count'];
        }

        $holdUuid = (string) Str::uuid();
        $ttlSeconds = (int) $event->hold_ttl_seconds;
        if ($ttlSeconds < 180) {
            $ttlSeconds = 180;
        }
        if ($ttlSeconds > 300) {
            $ttlSeconds = 300;
        }

        $createdAt = now();
        $expiresAt = $createdAt->copy()->addSeconds($ttlSeconds);

        $failedSeatIds = [];

        DB::beginTransaction();
        try {
            $idsUnique = array_values(array_unique($seatIds));
            sort($idsUnique);

            if (count($idsUnique) !== count($seatIds)) {
                DB::rollBack();

                return ['ok' => false, 'reason' => 'duplicate_seat_ids'];
            }

            $seats = Seat::query()
                ->where('event_id', $event->id)
                ->whereIn('id', $idsUnique)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($idsUnique)) {
                DB::rollBack();

                return ['ok' => false, 'reason' => 'seats_not_found'];
            }

            foreach ($seats as $seat) {
                $unavailable = false;
                if ($seat->status !== 'available') {
                    $unavailable = true;
                }
                if ($seat->current_hold_id !== null && $seat->current_hold_id !== '') {
                    $unavailable = true;
                }
                if ($unavailable) {
                    $failedSeatIds[] = (int) $seat->id;
                }
            }

            if (count($failedSeatIds) > 0) {
                DB::rollBack();
                $this->socketNotifier->emitToAnonSession(
                    $anonymousSessionId,
                    'seat:contention',
                    [
                        'eventId' => (string) $event->id,
                        'seatIds' => $failedSeatIds,
                        'message' => 'Aquest seient acaba de ser seleccionat per un altre usuari',
                    ],
                );

                return [
                    'ok' => false,
                    'reason' => 'seat_unavailable',
                    'seat_ids' => $failedSeatIds,
                ];
            }

            foreach ($seats as $seat) {
                $seat->status = 'held';
                $seat->current_hold_id = $holdUuid;
                $seat->held_until = $expiresAt;
                $seat->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $payload = [
            'event_id' => $event->id,
            'seat_ids' => $idsUnique,
            'anonymous_session_id' => $anonymousSessionId,
            'user_id' => $userId,
            'login_grace_applied' => false,
            'created_at' => $createdAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        $ttlCache = max(1, $expiresAt->timestamp - now()->timestamp);
        $this->holdCache->putRaw($holdUuid, json_encode($payload), $ttlCache);

        return [
            'ok' => true,
            'hold_id' => $holdUuid,
            'expires_at' => $expiresAt->toIso8601String(),
            'anonymous_session_id' => $anonymousSessionId,
            'seat_ids' => $idsUnique,
        ];
    }

    /**
     * @return array{ok: true, expires_at: string}|array{ok: false, reason: string}
     */
    public function applyLoginGrace(string $holdUuid, string $anonymousSessionId): array
    {
        $raw = $this->holdCache->getRaw($holdUuid);
        if ($raw === null) {
            return ['ok' => false, 'reason' => 'hold_not_found'];
        }

        $payload = json_decode((string) $raw, true);
        if (! is_array($payload)) {
            return ['ok' => false, 'reason' => 'hold_not_found'];
        }

        if (($payload['anonymous_session_id'] ?? '') !== $anonymousSessionId) {
            return ['ok' => false, 'reason' => 'session_mismatch'];
        }

        if (! empty($payload['login_grace_applied'])) {
            return ['ok' => false, 'reason' => 'grace_already_applied'];
        }

        $createdAt = Carbon::parse((string) $payload['created_at']);
        $expiresAt = Carbon::parse((string) $payload['expires_at']);
        $maxExpires = $createdAt->copy()->addSeconds(360);
        $newExpires = $expiresAt->copy()->addSeconds(120);
        if ($newExpires->gt($maxExpires)) {
            $newExpires = $maxExpires;
        }

        $payload['login_grace_applied'] = true;
        $payload['expires_at'] = $newExpires->toIso8601String();

        $remaining = max(1, $newExpires->timestamp - now()->timestamp);
        $this->holdCache->putRaw($holdUuid, json_encode($payload), $remaining);

        Seat::query()
            ->where('current_hold_id', $holdUuid)
            ->update(['held_until' => $newExpires]);

        $this->socketNotifier->emitToEventRoom((string) $payload['event_id'], 'countdown:resync', [
            'holdId' => $holdUuid,
            'expiresAt' => $newExpires->toIso8601String(),
        ]);

        return [
            'ok' => true,
            'expires_at' => $newExpires->toIso8601String(),
        ];
    }

    /**
     * @return array{ok: true}|array{ok: false, reason: string}
     */
    public function releaseHold(string $holdUuid, string $anonymousSessionId, ?int $userId): array
    {
        $raw = $this->holdCache->getRaw($holdUuid);
        if ($raw === null) {
            $this->seatDb->releaseSeatsByHoldUuid($holdUuid);

            return ['ok' => true];
        }

        $payload = json_decode((string) $raw, true);
        if (! is_array($payload)) {
            $this->holdCache->forget($holdUuid);
            $this->seatDb->releaseSeatsByHoldUuid($holdUuid);

            return ['ok' => true];
        }

        $sessionOk = ($payload['anonymous_session_id'] ?? '') === $anonymousSessionId;
        $userOk = false;
        if ($userId !== null && isset($payload['user_id']) && (int) $payload['user_id'] === $userId) {
            $userOk = true;
        }

        if (! $sessionOk && ! $userOk) {
            return ['ok' => false, 'reason' => 'forbidden'];
        }

        $this->holdCache->forget($holdUuid);
        $this->seatDb->releaseSeatsByHoldUuid($holdUuid);

        return ['ok' => true];
    }

    /**
     * Allibera cache Redis i files de seients per a un hold (p. ex. pagament denegat, T021).
     */
    public function forceReleaseHold(string $holdUuid): void
    {
        $this->holdCache->forget($holdUuid);
        $this->seatDb->releaseSeatsByHoldUuid($holdUuid);
    }

    public function forgetHoldCache(string $holdUuid): void
    {
        $this->holdCache->forget($holdUuid);
    }

    /**
     * @return array{expires_at: ?string, server_time: string}|null
     */
    public function getHoldTime(string $holdUuid): ?array
    {
        $raw = $this->holdCache->getRaw($holdUuid);
        if ($raw === null) {
            return null;
        }

        $payload = json_decode((string) $raw, true);
        if (! is_array($payload)) {
            return null;
        }

        $expires = null;
        if (isset($payload['expires_at'])) {
            $expires = (string) $payload['expires_at'];
        }

        return [
            'expires_at' => $expires,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Valida el hold (cache + sessió + caducitat / user_id) abans de crear una comanda.
     *
     * @return array{ok: true, payload: array<string, mixed>}|array{ok: false, reason: string}
     */
    public function prepareHoldForOrder(string $holdUuid, int $userId, string $anonymousSessionId): array
    {
        $raw = $this->holdCache->getRaw($holdUuid);
        if ($raw === null) {
            return ['ok' => false, 'reason' => 'hold_not_found'];
        }

        $payload = json_decode((string) $raw, true);
        if (! is_array($payload)) {
            return ['ok' => false, 'reason' => 'hold_not_found'];
        }

        if (($payload['anonymous_session_id'] ?? '') !== $anonymousSessionId) {
            return ['ok' => false, 'reason' => 'session_mismatch'];
        }

        if (empty($payload['expires_at']) || empty($payload['seat_ids']) || ! is_array($payload['seat_ids'])) {
            return ['ok' => false, 'reason' => 'hold_not_found'];
        }

        $expiresAt = Carbon::parse((string) $payload['expires_at']);
        if ($expiresAt->isPast()) {
            return ['ok' => false, 'reason' => 'hold_expired'];
        }

        $existingUserId = $payload['user_id'] ?? null;
        if ($existingUserId !== null && (int) $existingUserId !== $userId) {
            return ['ok' => false, 'reason' => 'user_mismatch'];
        }

        return ['ok' => true, 'payload' => $payload];
    }

    /**
     * Associa l’usuari autenticat al hold a la cache (mateix TTL restant).
     *
     * @param  array<string, mixed>  $payload
     */
    public function persistUserOnHold(string $holdUuid, array $payload, int $userId): void
    {
        $payload['user_id'] = $userId;
        $expiresAt = Carbon::parse((string) $payload['expires_at']);
        $remaining = max(1, $expiresAt->timestamp - now()->timestamp);
        $this->holdCache->putRaw($holdUuid, json_encode($payload), $remaining);
    }
}
