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
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized access.');
        }

        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        if (! $user->institution) {
            abort(403, 'Unauthorized access.');
        }

        if (! $user->institution->hasAddon($addon)) {
            abort(403, 'This feature requires the '.$addon.' add-on.');
        }

        return $next($request);
    }
}
