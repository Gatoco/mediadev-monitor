<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El Chaos Lab solo existe con CHAOS_LAB_ENABLED=true (stack de clones). */
class EnsureChaosLabEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('chaos.enabled'), 404);

        return $next($request);
    }
}
