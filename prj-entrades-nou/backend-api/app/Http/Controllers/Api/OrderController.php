<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderPaymentConfirmationService;
use App\Services\Order\PendingPaymentOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct (
        private readonly PendingPaymentOrderService $pendingPaymentOrderService,
        private readonly OrderPaymentConfirmationService $orderPaymentConfirmationService,
    ) {}

    public function store (Request $request): JsonResponse
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

    public function confirmPayment (Request $request, Order $order): JsonResponse
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

    private function confirmErrorMessage (string $reason): string
    {
        return match ($reason) {
            'forbidden' => 'No autoritzat',
            'invalid_state' => 'La comanda no es pot confirmar en aquest estat',
            default => 'No s\'ha pogut confirmar el pagament',
        };
    }
}
