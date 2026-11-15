<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Autentica la app Android con `Authorization: Bearer <CHAOS_APP_TOKEN>`. */
class EnsureChaosAppToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('chaos.app_token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['error' => 'Token de la app inválido'], 401);
        }

        return $next($request);
    }
}
