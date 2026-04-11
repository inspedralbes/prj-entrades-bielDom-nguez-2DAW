<?php

namespace App\Services\Social;

use App\Models\FriendInvite;
use App\Models\User;

/**
 * Consultes d’amistat acceptada (T036) per transferències i llistats.
 */
class FriendshipQuery
{
    public function areFriends (User $a, User $b): bool
    {
        if ((int) $a->id === (int) $b->id) {
            return false;
        }

        return FriendInvite::query()
            ->where('status', FriendInvite::STATUS_ACCEPTED)
            ->where(function ($q) use ($a, $b) {
                $q->where(function ($q) use ($a, $b) {
                    $q->where('sender_id', $a->id)->where('receiver_id', $b->id);
                })->orWhere(function ($q) use ($a, $b) {
                    $q->where('sender_id', $b->id)->where('receiver_id', $a->id);
                });
            })
            ->exists();
    }

    /**
     * @return array<int, array{id: int, username: string, name: string}>
     */
    public function friendProfilesFor (User $user): array
    {
        $me = (int) $user->id;
        $invites = FriendInvite::query()
            ->where('status', FriendInvite::STATUS_ACCEPTED)
            ->where(function ($q) use ($me) {
                $q->where('sender_id', $me)->orWhere('receiver_id', $me);
            })
            ->get();

        $ids = [];
        foreach ($invites as $inv) {
            $other = (int) $inv->sender_id === $me ? (int) $inv->receiver_id : (int) $inv->sender_id;
            if ($other > 0) {
                $ids[$other] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', array_keys($ids))
            ->orderBy('username')
            ->get(['id', 'username', 'name'])
            ->map(static fn (User $u) => [
                'id' => (int) $u->id,
                'username' => (string) $u->username,
                'name' => (string) $u->name,
            ])
            ->values()
            ->all();
    }
}
