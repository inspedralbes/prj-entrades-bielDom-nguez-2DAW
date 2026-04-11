<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticket\JwtTicketService;
use App\Services\Ticket\SocketTicketSvgClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TicketController extends Controller
{
    public function __construct (
        private readonly JwtTicketService $jwtTicketService,
        private readonly SocketTicketSvgClient $socketTicketSvgClient,
    ) {}

    /**
     * GET /api/tickets/{ticketId}/qr — SVG del QR amb payload JWT de l’entrada (T027).
     */
    public function showQr (Request $request, string $ticketId): Response|JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $ticket = Ticket::query()->find($ticketId);
        if ($ticket === null) {
            return response()->json(['message' => 'Entrada no trobada'], 404);
        }

        $ticket->loadMissing(['orderLine.order']);

        $orderLine = $ticket->orderLine;
        if ($orderLine === null) {
            return response()->json(['message' => 'Entrada no trobada'], 404);
        }

        $order = $orderLine->order;
        if ($order === null || (int) $order->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Entrada no trobada'], 404);
        }

        if ($ticket->status !== Ticket::STATUS_VENUDA) {
            return response()->json(['message' => 'L’entrada no és vàlida per al QR'], 409);
        }

        $this->ensureFreshTicketCredential($ticket);

        $eventId = (int) $order->event_id;
        $jwt = $this->jwtTicketService->issueForTicket($ticket->fresh(), $user, $eventId);

        $svg = $this->socketTicketSvgClient->svgForQrPayload($jwt);
        if ($svg === null) {
            return response()->json(['message' => 'Generació de QR no disponible'], 503);
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ensureFreshTicketCredential (Ticket $ticket): void
    {
        $ttl = (int) config('jwt.ticket_ttl_seconds', 900);
        $expiresAt = $ticket->jwt_expires_at;
        if ($expiresAt === null || $expiresAt->isPast()) {
            $ticket->jwt_expires_at = now()->addSeconds($ttl);
            $ticket->save();
        }
    }
}
