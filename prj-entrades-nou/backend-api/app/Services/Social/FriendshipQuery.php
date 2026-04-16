<?php

namespace App\Services\Social;

//================================ NAMESPACES / IMPORTS ============

use App\Models\FriendInvite;
use App\Models\User;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Consultes d’amistat acceptada (T036) per transferències i llistats.
 */
class FriendshipQuery
{
    public function areFriends(User $a, User $b): bool
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
    public function friendProfilesFor(User $user): array
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
            $other = 0;
            if ((int) $inv->sender_id === $me) {
                $other = (int) $inv->receiver_id;
            } else {
                $other = (int) $inv->sender_id;
            }
            if ($other > 0) {
                $ids[$other] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', array_keys($ids))
            ->orderBy('username')
            ->get(['id', 'username', 'name']);

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id' => (int) $u->id,
                'username' => (string) $u->username,
                'name' => (string) $u->name,
            ];
        }

        return $out;
    }

    /**
     * Mateix que {@see friendProfilesFor} amb filtre opcional per substring a username o name (FR-041).
     *
     * @return array<int, array{id: int, username: string, name: string}>
     */
    public function friendProfilesForSearch(User $user, ?string $q): array
    {
        $profiles = $this->friendProfilesFor($user);
        if ($q === null) {
            return $profiles;
        }

        $trimmed = trim($q);
        if ($trimmed === '') {
            return $profiles;
        }

        $needle = mb_strtolower($trimmed);
        $out = [];
        foreach ($profiles as $p) {
            $u = mb_strtolower($p['username']);
            $n = mb_strtolower($p['name']);
            $matchUsername = str_contains($u, $needle);
            $matchName = str_contains($n, $needle);
            if ($matchUsername || $matchName) {
                $out[] = $p;
            }
        }

        return $out;
    }
}
