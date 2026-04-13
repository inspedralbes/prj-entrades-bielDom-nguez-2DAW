<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderLine;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticket\JwtTicketService;
use App\Services\Ticket\LocalTicketSvgQrService;
use App\Services\Ticket\SocketTicketSvgClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TicketController extends Controller
{
    public function __construct(
        private readonly JwtTicketService $jwtTicketService,
        private readonly SocketTicketSvgClient $socketTicketSvgClient,
        private readonly LocalTicketSvgQrService $localTicketSvgQrService,
    ) {}

    /**
     * GET /api/tickets — historial d’entrades de l’usuari autenticat (T028).
     */
    public function index(Request $request): JsonResponse
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
                'orderLine.order.event.venue',
                'orderLine.seat',
            ])
            ->orderByDesc('created_at')
            ->get();

        $payload = $tickets->map(function (Ticket $ticket) {
            $line = $ticket->orderLine;
            $order = $line?->order;
            $event = $order?->event;
            $seat = $line?->seat;
            $venue = $event?->venue;

            $seatPresentation = $this->seatPresentationFromLine($line, $seat);

            $eventPayload = null;
            if ($event !== null) {
                $venuePayload = null;
                if ($venue !== null) {
                    $venuePayload = [
                        'name' => $venue->name,
                    ];
                }
                $eventPayload = [
                    'id' => $event->id,
                    'name' => $event->name,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'image_url' => $event->image_url,
                    'venue' => $venuePayload,
                ];
            }

            return [
                'id' => $ticket->id,
                'public_uuid' => $ticket->public_uuid,
                'status' => $ticket->status,
                'jwt_expires_at' => $ticket->jwt_expires_at?->toIso8601String(),
                'used_at' => $ticket->used_at?->toIso8601String(),
                'order_id' => $order?->id,
                'event' => $eventPayload,
                'seat' => $seatPresentation,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json(['tickets' => $payload]);
    }

    /**
     * GET /api/tickets/{ticketId}/qr — SVG del QR amb payload JWT de l’entrada (T027).
     */
    public function showQr(Request $request, string $ticketId): Response|JsonResponse
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
        if ($svg === null || $svg === '') {
            $svg = $this->localTicketSvgQrService->svgForPayload($jwt);
        }
        if ($svg === null || $svg === '') {
            return response()->json(['message' => 'Generació de QR no disponible'], 503);
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Ubicació llegible (fila / seient) des de `seats.external_seat_key` o `order_lines.seat_key` (mapa cinema).
     *
     * @return array{id: ?int, key: ?string, row: ?int, col: ?int, label: ?string}|null
     */
    private function seatPresentationFromLine(?OrderLine $line, ?Seat $seat): ?array
    {
        if ($line === null) {
            return null;
        }

        $key = null;
        if ($seat !== null && $seat->external_seat_key !== null && $seat->external_seat_key !== '') {
            $key = (string) $seat->external_seat_key;
        }
        if ($key === null && $line->seat_key !== null && $line->seat_key !== '') {
            $key = (string) $line->seat_key;
        }

        if ($key === null) {
            return null;
        }

        $row = null;
        $col = null;
        if (preg_match('/section_\\d+-row_(\\d+)-seat_(\\d+)/', $key, $m)) {
            $row = (int) $m[1];
            $col = (int) $m[2];
        }

        $label = null;
        if ($row !== null && $col !== null) {
            $label = 'Fila '.$row.', seient '.$col;
        }

        return [
            'id' => $seat !== null ? (int) $seat->id : null,
            'key' => $key,
            'row' => $row,
            'col' => $col,
            'label' => $label,
        ];
    }

    private function ensureFreshTicketCredential(Ticket $ticket): void
    {
        $ttl = (int) config('jwt.ticket_ttl_seconds', 900);
        $expiresAt = $ticket->jwt_expires_at;
        if ($expiresAt === null || $expiresAt->isPast()) {
            $ticket->jwt_expires_at = now()->addSeconds($ttl);
            $ticket->save();
        }
    }
}
