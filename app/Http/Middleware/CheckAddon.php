<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAddon
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $addon): Response
    {
        if (! $request->user() || ! $request->user()->institution) {
            abort(403, 'Unauthorized access.');
        }

        if (! $request->user()->institution->hasAddon($addon)) {
            abort(403, 'This feature requires the '.$addon.' add-on.');
        }

        return $next($request);
    }
}
