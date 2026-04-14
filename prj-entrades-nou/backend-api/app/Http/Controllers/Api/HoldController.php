<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Auth\JwtTokenService;
use App\Services\Hold\SeatHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HoldController extends Controller
{
    public function __construct(
        private readonly SeatHoldService $seatHoldService,
        private readonly JwtTokenService $jwtTokenService,
    ) {}

    public function store(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $validated = $request->validate([
            'seat_ids' => ['required', 'array', 'min:1', 'max:6'],
            'seat_ids.*' => ['integer'],
            'anonymous_session_id' => ['nullable', 'string', 'max:128'],
        ]);

        $anon = $validated['anonymous_session_id'] ?? '';
        if ($anon === '') {
            $anon = (string) Str::uuid();
        }

        $userId = $this->optionalUserId($request);

        $result = $this->seatHoldService->createHold(
            $event,
            $validated['seat_ids'],
            $anon,
            $userId,
        );

        if ($result['ok'] === true) {
            Log::info('hold.created', [
                'event_id' => (int) $event->id,
                'hold_id' => $result['hold_id'],
                'seat_count' => count($result['seat_ids'] ?? []),
            ]);
        }

        if ($result['ok'] === false) {
            if ($result['reason'] === 'seats_not_found') {
                return response()->json(['message' => 'Seients no vàlids per a aquest esdeveniment'], 404);
            }
            if ($result['reason'] === 'duplicate_seat_ids') {
                return response()->json(['message' => 'No es poden repetir seients'], 422);
            }
            if ($result['reason'] === 'invalid_seat_count') {
                return response()->json(['message' => 'Entre 1 i 6 seients'], 422);
            }
            if ($result['reason'] === 'seat_unavailable') {
                return response()->json([
                    'message' => 'Un o més seients no estan disponibles',
                    'seat_ids' => $result['seat_ids'] ?? [],
                ], 409);
            }

            return response()->json(['message' => 'No s\'ha pogut crear el hold'], 400);
        }

        return response()->json([
            'hold_id' => $result['hold_id'],
            'expires_at' => $result['expires_at'],
            'anonymous_session_id' => $result['anonymous_session_id'],
            'seat_ids' => $result['seat_ids'],
        ], 201);
    }

    public function loginGrace(Request $request, string $holdId): JsonResponse
    {
        $validated = $request->validate([
            'anonymous_session_id' => ['required', 'string', 'max:128'],
        ]);

        $result = $this->seatHoldService->applyLoginGrace($holdId, $validated['anonymous_session_id']);

        if ($result['ok'] === false) {
            if ($result['reason'] === 'hold_not_found') {
                return response()->json(['message' => 'Hold inexistent o caducat'], 404);
            }
            if ($result['reason'] === 'session_mismatch') {
                return response()->json(['message' => 'Sessió anònima no coincideix'], 403);
            }
            if ($result['reason'] === 'grace_already_applied') {
                return response()->json(['message' => 'La pròrroga de login ja s\'ha aplicat'], 403);
            }

            return response()->json(['message' => 'No s\'ha pogut aplicar la gràcia'], 400);
        }

        return response()->json([
            'expires_at' => $result['expires_at'],
        ]);
    }

    public function destroy(Request $request, string $holdId): JsonResponse
    {
        $validated = $request->validate([
            'anonymous_session_id' => ['nullable', 'string', 'max:128'],
        ]);

        $anon = $validated['anonymous_session_id'] ?? '';
        if ($anon === '') {
            $q = $request->query('anonymous_session_id');
            if (is_string($q) && $q !== '') {
                $anon = $q;
            }
        }

        $userId = $this->optionalUserId($request);

        if ($anon === '' && $userId === null) {
            return response()->json(['message' => 'Cal anonymous_session_id o JWT'], 422);
        }

        $result = $this->seatHoldService->releaseHold($holdId, $anon, $userId);

        if ($result['ok'] === false) {
            if ($result['reason'] === 'forbidden') {
                return response()->json(['message' => 'No autoritzat a alliberar aquest hold'], 403);
            }

            return response()->json(['message' => 'Error en alliberar el hold'], 400);
        }

        return response()->json(['message' => 'Hold alliberat']);
    }

    public function time(string $holdId): JsonResponse
    {
        $data = $this->seatHoldService->getHoldTime($holdId);
        if ($data === null) {
            return response()->json(['message' => 'Hold inexistent o caducat'], 404);
        }

        return response()->json($data)->withHeaders([
            'X-Server-Time' => $data['server_time'],
        ]);
    }

    private function optionalUserId(Request $request): ?int
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            return null;
        }

        try {
            $payload = $this->jwtTokenService->decode($token);

            return (int) $payload->sub;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
