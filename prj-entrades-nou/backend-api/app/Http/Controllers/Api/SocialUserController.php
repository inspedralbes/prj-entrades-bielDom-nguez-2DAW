<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FriendInvite;
use App\Models\User;
use App\Services\Social\FriendshipQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cerca d’usuaris registrats i perfil públic per convidar (sense exposar email).
 */
class SocialUserController extends Controller
{
    public function __construct(
        private readonly FriendshipQuery $friendshipQuery,
    )
    {
    }

    /**
     * GET /api/social/discover/search?q= — per nom o nom d’usuari (mín. 2 caràcters).
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $raw = $request->query('q', '');
        if (!is_string($raw)) {
            return response()->json(['users' => []], 200);
        }

        $needle = trim($raw);
        if (mb_strlen($needle) < 2) {
            return response()->json(['users' => []]);
        }

        if (mb_strlen($needle) > 80) {
            $needle = mb_substr($needle, 0, 80);
        }

        $escaped = mb_strtolower($needle);
        $like = '%'.addcslashes($escaped, '%_\\').'%';

        $rows = User::query()
            ->whereKeyNot($user->id)
            ->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'username', 'name']);

        $out = [];
        foreach ($rows as $u) {
            $out[] = [
                'id' => (int) $u->id,
                'username' => (string) $u->username,
                'name' => (string) $u->name,
            ];
        }

        return response()->json(['users' => $out]);
    }

    /**
     * GET /api/social/users/{userId} — dades mínimes + relació amb l’usuari autenticat.
     */
    public function publicProfile(Request $request, string $userId): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $other = User::query()->find($userId);
        if ($other === null) {
            return response()->json(['message' => 'Usuari no trobat'], 404);
        }

        if ((int) $other->id === (int) $user->id) {
            return response()->json([
                'id' => (int) $other->id,
                'username' => (string) $other->username,
                'name' => (string) $other->name,
                'relationship' => 'self',
                'pending_invite_id' => null,
            ]);
        }

        $resolved = $this->resolveRelationship($user, $other);

        return response()->json([
            'id' => (int) $other->id,
            'username' => (string) $other->username,
            'name' => (string) $other->name,
            'relationship' => $resolved['status'],
            'pending_invite_id' => $resolved['pending_invite_id'],
        ]);
    }

    /**
     * @return array{status: string, pending_invite_id: string|null}
     */
    private function resolveRelationship(User $me, User $other): array
    {
        if ($this->friendshipQuery->areFriends($me, $other)) {
            return ['status' => 'friend', 'pending_invite_id' => null];
        }

        $pending = FriendInvite::query()
            ->where('status', FriendInvite::STATUS_PENDING)
            ->where(function ($q) use ($me, $other) {
                $q->where(function ($q) use ($me, $other) {
                    $q->where('sender_id', $me->id)->where('receiver_id', $other->id);
                })->orWhere(function ($q) use ($me, $other) {
                    $q->where('sender_id', $other->id)->where('receiver_id', $me->id);
                });
            })
            ->first();

        if ($pending === null) {
            return ['status' => 'none', 'pending_invite_id' => null];
        }

        if ((int) $pending->sender_id === (int) $me->id) {
            return ['status' => 'pending_sent', 'pending_invite_id' => (string) $pending->id];
        }

        return ['status' => 'pending_received', 'pending_invite_id' => (string) $pending->id];
    }
}
