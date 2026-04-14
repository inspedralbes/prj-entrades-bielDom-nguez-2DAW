<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ticket\TicketScanValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function __construct(
        private readonly TicketScanValidationService $ticketScanValidationService,
    )
    {
    }

    /**
     * POST /api/validation/scan — JWT d’API (validador) + cos amb JWT de l’entrada (QR).
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $ticket = $this->ticketScanValidationService->validateAndMarkUsed(
            $user,
            $validated['token'],
        );

        return response()->json([
            'ticket_id' => (string) $ticket->id,
            'status' => $ticket->status,
            'used_at' => $ticket->used_at?->toIso8601String(),
        ]);
    }
}
