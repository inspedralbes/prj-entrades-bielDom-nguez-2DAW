<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ConfirmPaymentRequest;
use App\Http\Requests\Order\StoreCinemaSeatsRequest;
use App\Http\Requests\Order\StorePendingOrderRequest;
use App\Http\Requests\Order\StoreQuantityOrderRequest;
use App\Http\Requests\Order\StoreSeatsOrderRequest;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Hold\SeatHoldService;
use App\Services\Order\CinemaSeatOrderService;
use App\Services\Order\OrderApiResponseFactory;
use App\Services\Order\OrderPaymentConfirmationService;
use App\Services\Order\PendingPaymentOrderService;
use App\Services\Order\QuantityPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class OrderController extends Controller
{
    public function __construct(
        private readonly PendingPaymentOrderService $pendingPaymentOrderService,
        private readonly OrderPaymentConfirmationService $orderPaymentConfirmationService,
        private readonly QuantityPurchaseService $quantityPurchaseService,
        private readonly CinemaSeatOrderService $cinemaSeatOrderService,
        private readonly OrderApiResponseFactory $orderApiResponse,
    ) {}

    public function store(StorePendingOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validated();

        $result = $this->pendingPaymentOrderService->createFromHold(
            $user,
            $validated['hold_id'],
            $validated['anonymous_session_id'],
        );

        if ($result['ok'] === false) {
            $msg = $this->orderApiResponse->pendingHoldFailureMessage($result['reason']);

            return response()->json(['message' => $msg, 'reason' => $result['reason']], $result['http_status']);
        }

        $order = $result['order'];
        $payment = $result['payment'];

        Log::info('order.pending_payment', [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
        ]);

        return response()->json(
            $this->orderApiResponse->buildPendingHoldCreatedBody($order, $payment),
            201
        );
    }

    public function confirmPayment(ConfirmPaymentRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $result = $this->orderPaymentConfirmationService->confirmFinalPayment($user, $order);

        if ($result['ok'] === false) {
            $message = $result['message'] ?? $this->orderApiResponse->confirmPaymentErrorMessage($result['reason']);
            $payload = [
                'message' => $message,
                'reason' => $result['reason'],
            ];

            return response()->json($payload, $result['http_status']);
        }

        $o = $result['order'];

        Log::info('order.paid', [
            'order_id' => $o->id,
            'event_id' => $o->event_id,
        ]);

        return response()->json($this->orderApiResponse->buildConfirmPaidBody($o));
    }

    /**
     * Create order for quantity-based purchase (no seat selection).
     */
    public function storeQuantity(StoreQuantityOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validated();

        $result = $this->quantityPurchaseService->createQuantityOrder(
            $user,
            $validated['event_id'],
            $validated['quantity'],
        );

        if ($result['ok'] === false) {
            $msg = $this->orderApiResponse->quantityOrderFailureMessage($result['reason']);

            return response()->json(['message' => $msg, 'reason' => $result['reason']], $result['http_status']);
        }

        $order = $result['order'];
        $payment = $result['payment'];

        Log::info('order.quantity_pending_payment', [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
            'quantity' => $order->quantity,
        ]);

        return response()->json(
            $this->orderApiResponse->buildQuantityCreatedBody($order, $payment),
            201
        );
    }

    /**
     * Comanda amb seients del mapa cinema (claus Redis + seat_key a order_lines).
     */
    public function storeCinemaSeats(StoreCinemaSeatsRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validated();

        $result = $this->cinemaSeatOrderService->createPendingOrder(
            $user,
            $validated['event_id'],
            $validated['seat_keys'],
        );

        if ($result['ok'] === false) {
            $msg = $this->orderApiResponse->cinemaOrderFailureMessage($result);

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

        return response()->json(
            $this->orderApiResponse->buildCinemaCreatedBody($order, $payment),
            201
        );
    }

    /**
     * Create order for seat-based purchase.
     */
    public function storeSeats(StoreSeatsOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $validated = $request->validated();

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
            $msg = $this->orderApiResponse->seatHoldCreateFailureMessage($holdResult['reason']);

            return response()->json([
                'message' => $msg,
                'reason' => $holdResult['reason'],
                'seat_ids' => $holdResult['seat_ids'] ?? [],
            ], 422);
        }

        $orderResult = $this->pendingPaymentOrderService->createFromHold($user, $holdResult['hold_id'], $sessionId);

        if ($orderResult['ok'] === false) {
            app(SeatHoldService::class)->releaseHold($holdResult['hold_id'], $sessionId, $user->id);

            $msg = $this->orderApiResponse->pendingOrderAfterHoldFailureMessage($orderResult['reason']);

            return response()->json(['message' => $msg, 'reason' => $orderResult['reason']], 422);
        }

        $order = $orderResult['order'];
        $payment = $orderResult['payment'];

        Log::info('order.seats_pending_payment', [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
            'seat_count' => count($seatIds),
        ]);

        return response()->json(
            $this->orderApiResponse->buildSeatsCreatedBody($order, $seatIds, $payment),
            201
        );
    }
}
