<?php

namespace App\Services\Notification;

use App\Models\Event;
use App\Models\SocialNotification;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Socket\InternalSocketNotifier;

/**
 * Crea registres de social_notifications i avisa per Socket.IO (room user:{id}).
 */
class SocialNotificationService
{
    public function __construct (
        private readonly InternalSocketNotifier $socketNotifier,
    ) {}

    public function recordEventShare (User $actor, User $recipient, Event $event): void
    {
        $event->loadMissing('venue');

        $venue = $event->venue;
        $venueName = null;
        $venueCity = null;
        if ($venue !== null) {
            $venueName = $venue->name;
            $venueCity = $venue->city;
        }

        $basePayload = [
            'event_id' => (int) $event->id,
            'event_name' => (string) $event->name,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'image_url' => $event->image_url,
            'venue_name' => $venueName,
            'venue_city' => $venueCity,
        ];

        $toRecipient = $basePayload;
        $toRecipient['direction'] = 'received';
        $toRecipient['actor_user_id'] = (int) $actor->id;
        $toRecipient['actor_username'] = (string) $actor->username;

        $toActor = $basePayload;
        $toActor['direction'] = 'sent';
        $toActor['recipient_user_id'] = (int) $recipient->id;
        $toActor['recipient_username'] = (string) $recipient->username;

        $nRecipient = SocialNotification::query()->create([
            'user_id' => $recipient->id,
            'actor_user_id' => $actor->id,
            'type' => 'event_shared',
            'payload' => $toRecipient,
        ]);

        $nActor = SocialNotification::query()->create([
            'user_id' => $actor->id,
            'actor_user_id' => $recipient->id,
            'type' => 'event_shared',
            'payload' => $toActor,
        ]);

        $this->socketNotifier->emitToUser((int) $recipient->id, 'notification:new', [
            'notification_id' => (int) $nRecipient->id,
            'type' => 'event_shared',
        ]);

        $this->socketNotifier->emitToUser((int) $actor->id, 'notification:new', [
            'notification_id' => (int) $nActor->id,
            'type' => 'event_shared',
        ]);
    }

    public function recordTicketTransfer (User $from, User $to, Ticket $ticket): void
    {
        $ticket->loadMissing(['orderLine.order.event.venue', 'orderLine.seat']);

        $line = $ticket->orderLine;
        $order = $line?->order;
        $event = $order?->event;

        $eventName = 'Esdeveniment';
        $startsAt = null;
        $imageUrl = null;
        $venueName = null;
        if ($event !== null) {
            $eventName = (string) $event->name;
            $startsAt = $event->starts_at?->toIso8601String();
            $imageUrl = $event->image_url;
            $event->loadMissing('venue');
            if ($event->venue !== null) {
                $venueName = (string) $event->venue->name;
            }
        }

        $seatKey = null;
        if ($line !== null && $line->seat !== null) {
            $seatKey = (string) $line->seat->external_seat_key;
        }

        $description = $eventName;
        if ($seatKey !== null && $seatKey !== '') {
            $description = $eventName.' · '.$seatKey;
        }

        $basePayload = [
            'ticket_id' => (string) $ticket->id,
            'event_id' => $event !== null ? (int) $event->id : null,
            'event_name' => $eventName,
            'starts_at' => $startsAt,
            'image_url' => $imageUrl,
            'venue_name' => $venueName,
            'seat_key' => $seatKey,
            'description' => $description,
        ];

        $toRecipient = $basePayload;
        $toRecipient['direction'] = 'received';
        $toRecipient['actor_user_id'] = (int) $from->id;
        $toRecipient['actor_username'] = (string) $from->username;

        $toSender = $basePayload;
        $toSender['direction'] = 'sent';
        $toSender['recipient_user_id'] = (int) $to->id;
        $toSender['recipient_username'] = (string) $to->username;

        $nRecipient = SocialNotification::query()->create([
            'user_id' => $to->id,
            'actor_user_id' => $from->id,
            'type' => 'ticket_shared',
            'payload' => $toRecipient,
        ]);

        $nSender = SocialNotification::query()->create([
            'user_id' => $from->id,
            'actor_user_id' => $to->id,
            'type' => 'ticket_shared',
            'payload' => $toSender,
        ]);

        $this->socketNotifier->emitToUser((int) $to->id, 'notification:new', [
            'notification_id' => (int) $nRecipient->id,
            'type' => 'ticket_shared',
        ]);

        $this->socketNotifier->emitToUser((int) $from->id, 'notification:new', [
            'notification_id' => (int) $nSender->id,
            'type' => 'ticket_shared',
        ]);
    }
}
