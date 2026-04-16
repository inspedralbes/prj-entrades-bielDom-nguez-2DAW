<?php

namespace App\Http\Middleware;

//================================ NAMESPACES / IMPORTS ============

use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class AuthenticateJwt
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        $token = '';
        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
        }

        if ($token === '') {
            return response()->json(['message' => 'Token no proporcionat'], 401);
        }

        try {
            $payload = $this->jwtTokenService->decode($token);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Token invàlid o caducat'], 401);
        }

        $userId = $payload->sub;
        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['message' => 'Usuari no trobat'], 401);
        }

        $request->setUserResolver(static function () use ($user) {
            return $user;
        });

        // Spatie role middleware usa Auth::guard(); alineem amb JWT (rols user/validator/admin).
        Auth::guard('web')->setUser($user);

        return $next($request);
    }
}
