<?php

namespace App\Services\Social;

//================================ NAMESPACES / IMPORTS ============

use App\Models\FriendInvite;
use App\Models\User;
use App\Services\Notification\SocialNotificationService;
use Illuminate\Support\Str;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Llistat, creació i resolució d’invitacions d’amistat.
 */
class FriendInviteService
{
    public function __construct(
        private readonly SocialNotificationService $socialNotificationService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listInvites(User $user, string $direction): array
    {
        if (! in_array($direction, ['sent', 'received', 'all'], true)) {
            $direction = 'all';
        }

        $me = (int) $user->id;
        $q = FriendInvite::query()->orderByDesc('created_at');

        if ($direction === 'sent') {
            $q->where('sender_id', $me);
        } elseif ($direction === 'received') {
            $q->where(function ($q) use ($me, $user) {
                $q->where('receiver_id', $me)
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('receiver_id')
                            ->where('receiver_email', $user->email);
                    });
            });
        } else {
            $q->where(function ($q) use ($me, $user) {
                $q->where('sender_id', $me)
                    ->orWhere('receiver_id', $me)
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('receiver_id')
                            ->where('receiver_email', $user->email);
                    });
            });
        }

        $collected = $q->with(['sender', 'receiver'])->get();
        $rows = [];
        foreach ($collected as $i) {
            $rows[] = $this->invitePayload($i);
        }

        return $rows;
    }

    /**
     * @param  array{receiver_id?: int|null, receiver_email?: string|null}  $validated
     * @return array{ok: true, payload: array<string, mixed>, http_status: int}|array{ok: false, message: string, http_status: int}
     */
    public function createInvite(User $user, array $validated): array
    {
        $dup = FriendInvite::query()
            ->where('sender_id', $user->id)
            ->where('status', FriendInvite::STATUS_PENDING);

        if (! empty($validated['receiver_id'])) {
            $dup->where('receiver_id', (int) $validated['receiver_id']);
        } else {
            $dup->where('receiver_email', $validated['receiver_email']);
        }

        if ($dup->exists()) {
            return ['ok' => false, 'message' => 'Ja hi ha una sol·licitud pendent equivalent.', 'http_status' => 409];
        }

        $invite = FriendInvite::query()->create([
            'id' => (string) Str::uuid(),
            'sender_id' => $user->id,
            'receiver_id' => $validated['receiver_id'] ?? null,
            'receiver_email' => $validated['receiver_email'] ?? null,
            'status' => FriendInvite::STATUS_PENDING,
            'invite_token' => Str::random(40),
        ]);

        $inviteFresh = $invite->fresh()->load(['sender', 'receiver']);
        if (! empty($validated['receiver_id'])) {
            $recv = User::query()->find((int) $validated['receiver_id']);
            if ($recv !== null) {
                $this->socialNotificationService->recordFriendInviteReceived($user, $recv, $inviteFresh);
            }
        }

        return [
            'ok' => true,
            'payload' => $this->invitePayload($inviteFresh),
            'http_status' => 201,
        ];
    }

    /**
     * @return array{ok: true, payload: array<string, mixed>}|array{ok: false, message: string, http_status: int}
     */
    public function patchInvite(User $user, FriendInvite $invite, string $action): array
    {
        if ($invite->status !== FriendInvite::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'Aquesta sol·licitud ja està resolta', 'http_status' => 409];
        }

        $isReceiver = false;
        if ($invite->receiver_id !== null) {
            $isReceiver = (int) $invite->receiver_id === (int) $user->id;
        } elseif ($invite->receiver_email !== null) {
            $isReceiver = strcasecmp((string) $invite->receiver_email, (string) $user->email) === 0;
        }

        if (! $isReceiver) {
            return ['ok' => false, 'message' => 'No autoritzat', 'http_status' => 403];
        }

        if ($action === 'accept') {
            $invite->status = FriendInvite::STATUS_ACCEPTED;
            if ($invite->receiver_id === null) {
                $invite->receiver_id = $user->id;
            }
        } else {
            $invite->status = FriendInvite::STATUS_REJECTED;
        }
        $invite->save();

        if ($action === 'accept') {
            $after = $invite->fresh()->load(['sender']);
            if ($after->sender !== null) {
                $this->socialNotificationService->recordFriendInviteAccepted($user, $after->sender, $after);
            }
        }

        return [
            'ok' => true,
            'payload' => $this->invitePayload($invite->fresh()->load(['sender', 'receiver'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invitePayload(FriendInvite $invite): array
    {
        $senderName = '';
        $senderUsername = '';
        if ($invite->relationLoaded('sender') && $invite->sender !== null) {
            $senderName = (string) $invite->sender->name;
            $senderUsername = (string) $invite->sender->username;
        } else {
            $s = User::query()->find($invite->sender_id);
            if ($s !== null) {
                $senderName = (string) $s->name;
                $senderUsername = (string) $s->username;
            }
        }

        $receiverName = '';
        $receiverUsername = '';
        if ($invite->receiver_id !== null) {
            if ($invite->relationLoaded('receiver') && $invite->receiver !== null) {
                $receiverName = (string) $invite->receiver->name;
                $receiverUsername = (string) $invite->receiver->username;
            } else {
                $r = User::query()->find($invite->receiver_id);
                if ($r !== null) {
                    $receiverName = (string) $r->name;
                    $receiverUsername = (string) $r->username;
                }
            }
        }

        return [
            'id' => $invite->id,
            'sender_id' => (string) $invite->sender_id,
            'sender_name' => $senderName,
            'sender_username' => $senderUsername,
            'receiver_id' => $invite->receiver_id !== null ? (string) $invite->receiver_id : null,
            'receiver_name' => $receiverName,
            'receiver_username' => $receiverUsername,
            'receiver_email' => $invite->receiver_email,
            'status' => $invite->status,
            'invite_token' => $invite->invite_token,
            'created_at' => $invite->created_at?->toIso8601String(),
            'updated_at' => $invite->updated_at?->toIso8601String(),
        ];
    }
}
