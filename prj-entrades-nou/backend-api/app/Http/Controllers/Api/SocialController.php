<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Models\FriendInvite;
use App\Models\User;
use App\Services\Social\FriendInviteService;
use App\Services\Social\FriendshipQuery;
use App\Services\Social\SocialEventShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class SocialController extends Controller
{
    public function __construct(
        private readonly FriendshipQuery $friendshipQuery,
        private readonly FriendInviteService $friendInviteService,
        private readonly SocialEventShareService $socialEventShareService,
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

        $result = $this->socialEventShareService->shareEvent(
            $user,
            (int) $validated['event_id'],
            (int) $validated['to_user_id']
        );

        if ($result['ok'] === false) {
            return response()->json(['message' => $result['message']], $result['http_status']);
        }

        return response()->json(['shared' => true, 'event_id' => $result['event_id']], 201);
    }

    public function invitesIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $direction = $request->query('direction', 'all');
        if (! is_string($direction)) {
            $direction = 'all';
        }

        $rows = $this->friendInviteService->listInvites($user, $direction);

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

        $result = $this->friendInviteService->createInvite($user, $validated);

        if ($result['ok'] === false) {
            return response()->json(['message' => $result['message']], $result['http_status']);
        }

        return response()->json($result['payload'], $result['http_status']);
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
            return response()->json(['message' => 'Sol·licitud no trobada'], 404);
        }

        $result = $this->friendInviteService->patchInvite($user, $invite, $validated['action']);

        if ($result['ok'] === false) {
            return response()->json(['message' => $result['message']], $result['http_status']);
        }

        return response()->json($result['payload']);
    }
}
