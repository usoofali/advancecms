<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHubAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('hub_authenticated') !== true) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated Hub session.'], 401);
            }

            return redirect()->route('hub.login');
        }

        return $next($request);
    }
}
