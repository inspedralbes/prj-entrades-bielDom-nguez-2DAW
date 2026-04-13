<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutes internes cridades pel socket-server (mateix patró que X-Internal-Secret al Node).
 */
class VerifyInternalSocketSecret
{
    public function handle (Request $request, Closure $next): Response
    {
        $expected = config('services.socket.internal_secret');
        $got = $request->header('X-Internal-Secret', '');
        if ($expected !== null && $expected !== '' && $got !== $expected) {
            return response()->json(['message' => 'No autoritzat'], 403);
        }

        return $next($request);
    }
}
