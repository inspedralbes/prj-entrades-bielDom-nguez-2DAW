<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Hold\SeatHoldService;
use App\Services\Order\CinemaSeatOrderService;
use App\Services\Order\OrderPaymentConfirmationService;
use App\Services\Order\PendingPaymentOrderService;
use App\Services\Order\QuantityPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        private readonly PendingPaymentOrderService $pendingPaymentOrderService,
        private readonly OrderPaymentConfirmationService $orderPaymentConfirmationService,
        private readonly QuantityPurchaseService $quantityPurchaseService,
        private readonly CinemaSeatOrderService $cinemaSeatOrderService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'hold_id' => ['required', 'uuid'],
            'anonymous_session_id' => ['required', 'string', 'max:128'],
        ]);

        $result = $this->pendingPaymentOrderService->createFromHold(
            $user,
            $validated['hold_id'],
            $validated['anonymous_session_id'],
        );

        if ($result['ok'] === false) {
            $messages = [
                'hold_not_found' => 'Hold inexistent o caducat',
                'session_mismatch' => 'La sessió anònima no coincideix amb el hold',
                'hold_expired' => 'El temps de reserva ha expirat',
                'user_mismatch' => 'Aquest hold pertany a un altre usuari',
                'order_already_exists' => 'Ja existeix una comanda per aquest hold',
                'event_not_found' => 'Esdeveniment no trobat',
                'seat_mismatch' => 'Els seients del hold no coincideixen amb la base de dades',
                'seat_not_held' => 'Un o més seients ja no estan reservats',
            ];

            $msg = $messages[$result['reason']] ?? 'No s\'ha pogut crear la comanda';

            return response()->json(['message' => $msg, 'reason' => $result['reason']], $result['http_status']);
        }

        $order = $result['order'];
        $payment = $result['payment'];

        Log::info('order.pending_payment', [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'hold_id' => $order->hold_uuid,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
            'lines' => $order->orderLines->map(static function ($line) {
                return [
                    'id' => $line->id,
                    'seat_id' => $line->seat_id,
                    'unit_price' => (string) $line->unit_price,
                ];
            })->values()->all(),
        ], 201);
    }

    public function confirmPayment(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $result = $this->orderPaymentConfirmationService->confirmFinalPayment($user, $order);

        if ($result['ok'] === false) {
            $payload = [
                'message' => $result['message'] ?? $this->confirmErrorMessage($result['reason']),
                'reason' => $result['reason'],
            ];

            return response()->json($payload, $result['http_status']);
        }

        $o = $result['order'];

        Log::info('order.paid', [
            'order_id' => $o->id,
            'event_id' => $o->event_id,
        ]);

        return response()->json([
            'order_id' => $o->id,
            'state' => $o->state,
            'event_id' => $o->event_id,
            'tickets' => $o->orderLines->map(static function ($line) {
                $t = $line->ticket;
                if ($t === null) {
                    return null;
                }

                return [
                    'id' => $t->id,
                    'public_uuid' => $t->public_uuid,
                    'status' => $t->status,
                    'jwt_expires_at' => $t->jwt_expires_at?->toIso8601String(),
                ];
            })->filter()->values()->all(),
        ]);
    }

    private function confirmErrorMessage(string $reason): string
    {
        return match ($reason) {
            'forbidden' => 'No autoritzat',
            'invalid_state' => 'La comanda no es pot confirmar en aquest estat',
            default => 'No s\'ha pogut confirmar el pagament',
        };
    }

    /**
     * Create order for quantity-based purchase (no seat selection).
     */
    public function storeQuantity(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $result = $this->quantityPurchaseService->createQuantityOrder(
            $user,
            $validated['event_id'],
            $validated['quantity'],
        );

        if ($result['ok'] === false) {
            $msg = match ($result['reason']) {
                'invalid_quantity' => 'La quantitat ha de ser entre 1 i 6',
                'event_not_found' => 'Esdeveniment no trobat',
                default => 'No s\'ha pogut crear la comanda',
            };

            return response()->json(['message' => $msg, 'reason' => $result['reason']], $result['http_status']);
        }

        $order = $result['order'];
        $payment = $result['payment'];

        Log::info('order.quantity_pending_payment', [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
            'quantity' => $order->quantity,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'quantity' => $order->quantity,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
        ], 201);
    }

    /**
     * Comanda amb seients del mapa cinema (claus Redis + seat_key a order_lines).
     */
    public function storeCinemaSeats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'seat_keys' => ['required', 'array', 'min:1', 'max:6'],
            'seat_keys.*' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->cinemaSeatOrderService->createPendingOrder(
            $user,
            $validated['event_id'],
            $validated['seat_keys'],
        );

        if ($result['ok'] === false) {
            $msg = match ($result['reason']) {
                'invalid_seat_count' => 'Cal entre 1 i 6 seients',
                'invalid_seat' => $result['message'] ?? 'Seient no vàlid',
                'duplicate_seat' => 'No es poden repetir seients',
                'event_not_found' => 'Esdeveniment no trobat',
                'seat_sold' => $result['message'] ?? 'Seient venut',
                'not_held_by_user' => $result['message'] ?? 'Reserva no vàlida',
                default => 'No s\'ha pogut crear la comanda',
            };

            return response()->json([
                'message' => $msg,
                'reason' => $result['reason'],
            ], $result['http_status']);
        }

        $order = $result['order'];
        $payment = $result['payment'];

        Log::info('order.cinema_pending_payment', [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
        ], 201);
    }

    /**
     * Create order for seat-based purchase.
     */
    public function storeSeats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'seat_ids' => ['required', 'array', 'min:1', 'max:6'],
            'seat_ids.*' => ['required', 'integer'],
            'anonymous_session_id' => ['required', 'string', 'max:128'],
        ]);

        $seatIds = $validated['seat_ids'];
        $eventId = $validated['event_id'];
        $sessionId = $validated['anonymous_session_id'];

        $holdResult = app(SeatHoldService::class)->createHold(
            Event::findOrFail($eventId),
            $seatIds,
            $sessionId,
            $user->id
        );

        if ($holdResult['ok'] === false) {
            $msg = match ($holdResult['reason']) {
                'invalid_seat_count' => 'Cal seleccionar entre 1 i 6 seients',
                'seats_not_found' => 'Un o més seients no existeixen',
                'seat_unavailable' => 'Un o més seients ja no estan disponibles',
                'duplicate_seat_ids' => 'No es poden repetir seients',
                default => 'No s\'ha pogut crear la reserva',
            };

            return response()->json(['message' => $msg, 'reason' => $holdResult['reason'], 'seat_ids' => $holdResult['seat_ids'] ?? []], 422);
        }

        $orderResult = $this->pendingPaymentOrderService->createFromHold($user, $holdResult['hold_id'], $sessionId);

        if ($orderResult['ok'] === false) {
            app(SeatHoldService::class)->releaseHold($holdResult['hold_id'], $sessionId, $user->id);

            $msg = match ($orderResult['reason']) {
                'hold_not_found' => 'La reserva ha expirat',
                default => 'No s\'ha pogut crear la comanda',
            };

            return response()->json(['message' => $msg, 'reason' => $orderResult['reason']], 422);
        }

        $order = $orderResult['order'];
        $payment = $orderResult['payment'];

        Log::info('order.seats_pending_payment', [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
            'seat_count' => count($seatIds),
        ]);

        return response()->json([
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'seat_ids' => $seatIds,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
        ], 201);
    }
}
