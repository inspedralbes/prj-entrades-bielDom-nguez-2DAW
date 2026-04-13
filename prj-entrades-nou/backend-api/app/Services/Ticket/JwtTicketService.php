<?php

namespace App\Services\Ticket;

use App\Models\Ticket;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;

class JwtTicketService
{
    /**
     * JWT de credencial d’entrada (HS256). Claims: jti = id intern del ticket, pub = public_uuid per QR.
     */
    public function issueForTicket(Ticket $ticket, User $owner, int $eventId): string
    {
        $secret = Config::get('jwt.secret');
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('JWT_SECRET no definit (.env)');
        }

        $expiresAt = $ticket->jwt_expires_at;
        if ($expiresAt === null) {
            throw new \InvalidArgumentException('jwt_expires_at requerit per emetre JWT de ticket');
        }

        $algo = Config::get('jwt.algo', 'HS256');
        $now = time();
        $payload = [
            'jti' => $ticket->id,
            'sub' => (string) $owner->id,
            'pub' => $ticket->public_uuid,
            'evt' => $eventId,
            'typ' => 'ticket',
            'iat' => $now,
            'exp' => $expiresAt->getTimestamp(),
        ];

        return JWT::encode($payload, $secret, $algo);
    }

    /**
     * @return object{jti: string, sub: string, pub: string, evt: int, typ: string, iat: int, exp: int}
     */
    public function decode(string $jwt): object
    {
        $secret = Config::get('jwt.secret');
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('JWT_SECRET no definit (.env)');
        }

        $algo = Config::get('jwt.algo', 'HS256');
        $key = new Key($secret, $algo);

        return JWT::decode($jwt, $key);
    }
}
