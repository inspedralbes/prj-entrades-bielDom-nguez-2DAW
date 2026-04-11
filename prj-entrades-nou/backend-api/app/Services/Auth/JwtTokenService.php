<?php

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;

class JwtTokenService
{
    /**
     * Genera JWT d’API (HS256) amb sub i rols per a validació al socket-server.
     */
    public function issueForUser(User $user): string
    {
        $roles = [];
        foreach ($user->getRoleNames() as $roleName) {
            $roles[] = (string) $roleName;
        }

        $secret = Config::get('jwt.secret');
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('JWT_SECRET no definit (.env)');
        }

        $ttl = Config::get('jwt.ttl_seconds', 86400);
        $now = time();
        $payload = [
            'sub' => (string) $user->id,
            'roles' => $roles,
            'iat' => $now,
            'exp' => $now + $ttl,
        ];

        $algo = Config::get('jwt.algo', 'HS256');

        return JWT::encode($payload, $secret, $algo);
    }

    /**
     * @return object{sub: string, roles: array<int, string>, iat: int, exp: int}
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
