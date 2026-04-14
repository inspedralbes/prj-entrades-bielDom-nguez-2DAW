<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FriendInvite;
use App\Models\User;
use App\Services\Notification\SocialNotificationService;
use App\Services\Social\FriendshipQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SocialController extends Controller
{
    public function __construct(
        private readonly FriendshipQuery $friendshipQuery,
        private readonly SocialNotificationService $socialNotificationService,
    ) {}

    public function friends(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $q = $request->query('q');
        $qStr = null;
        if (is_string($q)) {
            $qStr = $q;
        }

        return response()->json([
            'friends' => $this->friendshipQuery->friendProfilesForSearch($user, $qStr),
        ]);
    }

    public function shareEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $to = User::query()->findOrFail($validated['to_user_id']);
        if ((int) $to->id === (int) $user->id) {
            return response()->json(['message' => 'No et pots enviar l’esdeveniment a tu mateix.'], 422);
        }

        if (! $this->friendshipQuery->areFriends($user, $to)) {
            return response()->json(['message' => 'Cal una amistat acceptada per compartir.'], 403);
        }

        $event = Event::query()->whereNull('hidden_at')->find($validated['event_id']);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $this->socialNotificationService->recordEventShare($user, $to, $event);

        return response()->json(['shared' => true, 'event_id' => (int) $event->id], 201);
    }

    public function invitesIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $direction = $request->query('direction', 'all');
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

        return response()->json(['invites' => $rows]);
    }

    public function invitesStore(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'receiver_id' => ['nullable', 'integer', 'exists:users,id'],
            'receiver_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        if (($validated['receiver_id'] ?? null) && ($validated['receiver_email'] ?? null)) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Només es pot indicar receiver_id o receiver_email.'],
            ]);
        }

        if (empty($validated['receiver_id']) && empty($validated['receiver_email'])) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Cal receiver_id o receiver_email.'],
            ]);
        }

        if (! empty($validated['receiver_id']) && (int) $validated['receiver_id'] === (int) $user->id) {
            throw ValidationException::withMessages([
                'receiver_id' => ['No et pots convidar a tu mateix.'],
            ]);
        }

        if (! empty($validated['receiver_email'])
            && strcasecmp((string) $validated['receiver_email'], (string) $user->email) === 0) {
            throw ValidationException::withMessages([
                'receiver_email' => ['No et pots convidar a tu mateix.'],
            ]);
        }

        $dup = FriendInvite::query()
            ->where('sender_id', $user->id)
            ->where('status', FriendInvite::STATUS_PENDING);

        if (! empty($validated['receiver_id'])) {
            $dup->where('receiver_id', (int) $validated['receiver_id']);
        } else {
            $dup->where('receiver_email', $validated['receiver_email']);
        }

        if ($dup->exists()) {
            return response()->json(['message' => 'Ja hi ha una invitació pendent equivalent.'], 409);
        }

        $invite = FriendInvite::query()->create([
            'id' => (string) Str::uuid(),
            'sender_id' => $user->id,
            'receiver_id' => $validated['receiver_id'] ?? null,
            'receiver_email' => $validated['receiver_email'] ?? null,
            'status' => FriendInvite::STATUS_PENDING,
            'invite_token' => Str::random(40),
        ]);

        return response()->json($this->invitePayload($invite->fresh()->load(['sender', 'receiver'])), 201);
    }

    public function invitesPatch(Request $request, string $inviteId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject'])],
        ]);

        $invite = FriendInvite::query()->find($inviteId);
        if ($invite === null) {
            return response()->json(['message' => 'Invitació no trobada'], 404);
        }

        if ($invite->status !== FriendInvite::STATUS_PENDING) {
            return response()->json(['message' => 'Aquesta invitació ja està resolta'], 409);
        }

        $isReceiver = false;
        if ($invite->receiver_id !== null) {
            $isReceiver = (int) $invite->receiver_id === (int) $user->id;
        } elseif ($invite->receiver_email !== null) {
            $isReceiver = strcasecmp((string) $invite->receiver_email, (string) $user->email) === 0;
        }

        if (! $isReceiver) {
            return response()->json(['message' => 'No autoritzat'], 403);
        }

        if ($validated['action'] === 'accept') {
            $invite->status = FriendInvite::STATUS_ACCEPTED;
            if ($invite->receiver_id === null) {
                $invite->receiver_id = $user->id;
            }
        } else {
            $invite->status = FriendInvite::STATUS_REJECTED;
        }
        $invite->save();

        return response()->json($this->invitePayload($invite->fresh()->load(['sender', 'receiver'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function invitePayload(FriendInvite $invite): array
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
