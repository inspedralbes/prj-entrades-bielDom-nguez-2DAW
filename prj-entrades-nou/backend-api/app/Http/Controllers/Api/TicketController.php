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
     * GET /api/tickets — historial d’entrades de l’usuari autenticat (T028).
     */
    public function index (Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $tickets = Ticket::query()
            ->whereHas('orderLine.order', static function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with([
                'orderLine.order.event',
                'orderLine.seat',
            ])
            ->orderByDesc('created_at')
            ->get();

        $payload = $tickets->map(static function (Ticket $ticket) {
            $line = $ticket->orderLine;
            $order = $line?->order;
            $event = $order?->event;
            $seat = $line?->seat;

            return [
                'id' => $ticket->id,
                'public_uuid' => $ticket->public_uuid,
                'status' => $ticket->status,
                'jwt_expires_at' => $ticket->jwt_expires_at?->toIso8601String(),
                'used_at' => $ticket->used_at?->toIso8601String(),
                'order_id' => $order?->id,
                'event' => $event === null ? null : [
                    'id' => $event->id,
                    'name' => $event->name,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                ],
                'seat' => $seat === null ? null : [
                    'id' => $seat->id,
                    'key' => $seat->external_seat_key,
                ],
                'created_at' => $ticket->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json(['tickets' => $payload]);
    }

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
