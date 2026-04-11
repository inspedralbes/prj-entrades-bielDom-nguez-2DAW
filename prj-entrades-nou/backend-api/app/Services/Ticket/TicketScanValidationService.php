<?php

namespace App\Services\Ticket;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Socket\InternalSocketNotifier;
use Illuminate\Support\Facades\DB;

class TicketScanValidationService
{
    public function __construct (
        private readonly JwtTicketService $jwtTicketService,
        private readonly InternalSocketNotifier $socketNotifier,
    ) {}

    /**
     * Valida el JWT llegit del QR, marca l’entrada com a utilitzada i notifica el titular per Socket (T030–T031).
     */
    public function validateAndMarkUsed (User $validator, string $ticketJwt): Ticket
    {
        if (! $validator->hasRole('validator')) {
            abort(403, 'Només el personal validador pot escanejar entrades.');
        }

        try {
            $claims = $this->jwtTicketService->decode($ticketJwt);
        } catch (\Throwable) {
            abort(400, 'Token d’entrada invàlid o caducat.');
        }

        if (! isset($claims->typ) || (string) $claims->typ !== 'ticket') {
            abort(400, 'Token d’entrada invàlid.');
        }

        $ticketId = isset($claims->jti) ? (string) $claims->jti : '';
        if ($ticketId === '') {
            abort(400, 'Token d’entrada invàlid.');
        }

        $ownerUserId = 0;

        DB::transaction(function () use ($validator, $ticketId, &$ownerUserId): void {
            $ticket = Ticket::query()
                ->lockForUpdate()
                ->with(['orderLine.order'])
                ->find($ticketId);

            if ($ticket === null) {
                abort(400, 'Entrada no trobada.');
            }

            $order = $ticket->orderLine?->order;
            if ($order === null) {
                abort(400, 'Entrada no trobada.');
            }

            $ownerUserId = (int) $order->user_id;

            if ($ticket->status !== Ticket::STATUS_VENUDA) {
                abort(400, 'Aquesta entrada ja ha estat utilitzada o no és vàlida.');
            }

            if ($ticket->used_at !== null) {
                abort(400, 'Aquesta entrada ja ha estat utilitzada.');
            }

            $ticket->status = Ticket::STATUS_UTILITZADA;
            $ticket->used_at = now();
            $ticket->validator_id = $validator->id;
            $ticket->save();
        });

        $ticket = Ticket::query()->with(['orderLine.order'])->findOrFail($ticketId);

        $this->socketNotifier->emitToUser($ownerUserId, 'ticket:validated', [
            'ticketId' => (string) $ticket->id,
            'status' => 'used',
            'userId' => (string) $ownerUserId,
        ]);

        return $ticket;
    }
}
