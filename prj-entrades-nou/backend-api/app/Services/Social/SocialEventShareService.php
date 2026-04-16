<?php

namespace App\Services\Social;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\User;
use App\Services\Notification\SocialNotificationService;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Compartir esdeveniments entre amics (notificacions).
 */
class SocialEventShareService
{
    public function __construct(
        private readonly FriendshipQuery $friendshipQuery,
        private readonly SocialNotificationService $socialNotificationService,
    ) {}

    /**
     * @return array{ok: true, event_id: int}|array{ok: false, message: string, http_status: int}
     */
    public function shareEvent(User $from, int $eventId, int $toUserId): array
    {
        $to = User::query()->findOrFail($toUserId);
        if ((int) $to->id === (int) $from->id) {
            return ['ok' => false, 'message' => 'No et pots enviar l’esdeveniment a tu mateix.', 'http_status' => 422];
        }

        if (! $this->friendshipQuery->areFriends($from, $to)) {
            return ['ok' => false, 'message' => 'Cal una amistat acceptada per compartir.', 'http_status' => 403];
        }

        $event = Event::query()->whereNull('hidden_at')->find($eventId);
        if ($event === null) {
            return ['ok' => false, 'message' => 'Esdeveniment no trobat', 'http_status' => 404];
        }

        $this->socialNotificationService->recordEventShare($from, $to, $event);

        return ['ok' => true, 'event_id' => (int) $event->id];
    }
}
