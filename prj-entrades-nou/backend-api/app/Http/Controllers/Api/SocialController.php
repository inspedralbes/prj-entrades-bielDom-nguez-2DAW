<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FriendInvite;
use App\Models\User;
use App\Services\Social\FriendshipQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SocialController extends Controller
{
    public function __construct (
        private readonly FriendshipQuery $friendshipQuery,
    ) {}

    public function friends (Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        return response()->json([
            'friends' => $this->friendshipQuery->friendProfilesFor($user),
        ]);
    }

    public function invitesIndex (Request $request): JsonResponse
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

        $rows = $q->get()->map(fn (FriendInvite $i) => $this->invitePayload($i))->values()->all();

        return response()->json(['invites' => $rows]);
    }

    public function invitesStore (Request $request): JsonResponse
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

        return response()->json($this->invitePayload($invite->fresh()), 201);
    }

    public function invitesPatch (Request $request, string $inviteId): JsonResponse
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

        return response()->json($this->invitePayload($invite->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function invitePayload (FriendInvite $invite): array
    {
        return [
            'id' => $invite->id,
            'sender_id' => (string) $invite->sender_id,
            'receiver_id' => $invite->receiver_id !== null ? (string) $invite->receiver_id : null,
            'receiver_email' => $invite->receiver_email,
            'status' => $invite->status,
            'invite_token' => $invite->invite_token,
            'created_at' => $invite->created_at?->toIso8601String(),
            'updated_at' => $invite->updated_at?->toIso8601String(),
        ];
    }
}
