<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $rows = SocialNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $list = [];
        foreach ($rows as $n) {
            $list[] = $this->serializeNotification($n);
        }

        return response()->json(['notifications' => $list]);
    }

    /**
     * POST /api/notifications/mark-all-read — marca totes les notificacions de l’usuari com a llegides (badge Social / peu).
     */
    public function markAllRead (Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $now = now();
        $marked = SocialNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => $now,
                'updated_at' => $now,
            ]);

        return response()->json(['marked' => $marked]);
    }

    /**
     * POST /api/notifications/mark-read-for-actor/{actorUserId} — llegides les notificacions d’un actor (badge «La teva gent»).
     */
    public function markReadForActor (Request $request, string $actorUserId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        if (! ctype_digit((string) $actorUserId)) {
            return response()->json(['message' => 'ID invàlid'], 422);
        }

        $actorId = (int) $actorUserId;
        $now = now();
        $marked = SocialNotification::query()
            ->where('user_id', $user->id)
            ->where('actor_user_id', $actorId)
            ->whereNull('read_at')
            ->update([
                'read_at' => $now,
                'updated_at' => $now,
            ]);

        return response()->json(['marked' => $marked]);
    }

    public function update (Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'read' => ['required', 'boolean'],
        ]);

        $n = SocialNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first();

        if ($n === null) {
            return response()->json(['message' => 'Notificació no trobada'], 404);
        }

        if ($validated['read'] === true) {
            if ($n->read_at === null) {
                $n->read_at = now();
                $n->save();
            }
        } else {
            $n->read_at = null;
            $n->save();
        }

        return response()->json($this->serializeNotification($n->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(SocialNotification $n): array
    {
        return [
            'id' => (int) $n->id,
            'type' => (string) $n->type,
            'payload' => $n->payload,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
