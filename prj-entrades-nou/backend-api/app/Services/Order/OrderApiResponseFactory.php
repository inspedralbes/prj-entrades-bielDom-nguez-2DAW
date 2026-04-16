<?php

namespace App\Services\Order;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Order;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Construcció de payloads JSON per a fluxos de comanda (sense dependències HTTP).
 */
class OrderApiResponseFactory
{
    /**
     * @return array<int, array{id: int, seat_id: int|null, unit_price: string}>
     */
    public function buildPendingHoldLinesPayload(Order $order): array
    {
        $linesPayload = [];
        foreach ($order->orderLines as $line) {
            $linesPayload[] = [
                'id' => $line->id,
                'seat_id' => $line->seat_id,
                'unit_price' => (string) $line->unit_price,
            ];
        }

        return $linesPayload;
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public function buildPendingHoldCreatedBody(Order $order, array $payment): array
    {
        return [
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'hold_id' => $order->hold_uuid,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
            'lines' => $this->buildPendingHoldLinesPayload($order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConfirmPaidBody(Order $order): array
    {
        $ticketsPayload = [];
        foreach ($order->orderLines as $line) {
            $t = $line->ticket;
            if ($t === null) {
                continue;
            }
            $jwtExpires = null;
            if ($t->jwt_expires_at !== null) {
                $jwtExpires = $t->jwt_expires_at->toIso8601String();
            }
            $ticketsPayload[] = [
                'id' => $t->id,
                'public_uuid' => $t->public_uuid,
                'status' => $t->status,
                'jwt_expires_at' => $jwtExpires,
            ];
        }

        return [
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'tickets' => $ticketsPayload,
        ];
    }

    /**
     * Missatge d’error per a `confirm-payment` quan el servei no en porta un d’específic.
     */
    public function confirmPaymentErrorMessage(string $reason): string
    {
        if ($reason === 'forbidden') {
            return 'No autoritzat';
        }
        if ($reason === 'invalid_state') {
            return 'La comanda no es pot confirmar en aquest estat';
        }

        return 'No s\'ha pogut confirmar el pagament';
    }

    public function pendingHoldFailureMessage(string $reason): string
    {
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

        if (isset($messages[$reason])) {
            return $messages[$reason];
        }

        return 'No s\'ha pogut crear la comanda';
    }

    public function quantityOrderFailureMessage(string $reason): string
    {
        if ($reason === 'invalid_quantity') {
            return 'La quantitat ha de ser entre 1 i 6';
        }
        if ($reason === 'event_not_found') {
            return 'Esdeveniment no trobat';
        }

        return 'No s\'ha pogut crear la comanda';
    }

    /**
     * @param  array{reason: string, message?: string|null}  $result
     */
    public function cinemaOrderFailureMessage(array $result): string
    {
        $reason = $result['reason'];
        if ($reason === 'invalid_seat_count') {
            return 'Cal entre 1 i 6 seients';
        }
        if ($reason === 'invalid_seat') {
            if (isset($result['message']) && is_string($result['message']) && $result['message'] !== '') {
                return $result['message'];
            }

            return 'Seient no vàlid';
        }
        if ($reason === 'duplicate_seat') {
            return 'No es poden repetir seients';
        }
        if ($reason === 'event_not_found') {
            return 'Esdeveniment no trobat';
        }
        if ($reason === 'seat_sold') {
            if (isset($result['message']) && is_string($result['message']) && $result['message'] !== '') {
                return $result['message'];
            }

            return 'Seient venut';
        }
        if ($reason === 'not_held_by_user') {
            if (isset($result['message']) && is_string($result['message']) && $result['message'] !== '') {
                return $result['message'];
            }

            return 'Reserva no vàlida';
        }

        return 'No s\'ha pogut crear la comanda';
    }

    public function seatHoldCreateFailureMessage(string $reason): string
    {
        if ($reason === 'invalid_seat_count') {
            return 'Cal seleccionar entre 1 i 6 seients';
        }
        if ($reason === 'seats_not_found') {
            return 'Un o més seients no existeixen';
        }
        if ($reason === 'seat_unavailable') {
            return 'Un o més seients ja no estan disponibles';
        }
        if ($reason === 'duplicate_seat_ids') {
            return 'No es poden repetir seients';
        }

        return 'No s\'ha pogut crear la reserva';
    }

    public function pendingOrderAfterHoldFailureMessage(string $reason): string
    {
        if ($reason === 'hold_not_found') {
            return 'La reserva ha expirat';
        }

        return 'No s\'ha pogut crear la comanda';
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public function buildQuantityCreatedBody(Order $order, array $payment): array
    {
        return [
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'quantity' => $order->quantity,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public function buildCinemaCreatedBody(Order $order, array $payment): array
    {
        return [
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
        ];
    }

    /**
     * @param  list<int>  $seatIds
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public function buildSeatsCreatedBody(Order $order, array $seatIds, array $payment): array
    {
        return [
            'order_id' => $order->id,
            'state' => $order->state,
            'event_id' => $order->event_id,
            'seat_ids' => $seatIds,
            'currency' => $order->currency,
            'total_amount' => (string) $order->total_amount,
            'payment' => $payment,
        ];
    }
}
