<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticket\TicketTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketTransferController extends Controller
{
    public function __construct (
        private readonly TicketTransferService $ticketTransferService,
    ) {}

    public function store (Request $request, string $ticketId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ticket = Ticket::query()->find($ticketId);
        if ($ticket === null) {
            return response()->json(['message' => 'Entrada no trobada'], 404);
        }

        $to = User::query()->findOrFail($validated['to_user_id']);

        $result = $this->ticketTransferService->transfer($user, $ticket, $to);

        if ($result['ok'] === false) {
            return response()->json(['message' => $result['message']], $result['http_status']);
        }

        $t = $result['ticket'];

        Log::info('ticket.transferred', [
            'ticket_id' => (string) $t->id,
            'from_user_id' => (int) $user->id,
            'to_user_id' => (int) $to->id,
        ]);

        return response()->json([
            'ticket_id' => (string) $t->id,
            'public_uuid' => $t->public_uuid,
            'jwt_expires_at' => $t->jwt_expires_at?->toIso8601String(),
            'message' => 'Transferència completada; cal tornar a obtenir el QR.',
        ]);
    }
}
