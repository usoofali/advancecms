<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExamModuleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || ! $user->institution || ! $user->institution->hasAddon('exam_module')) {
            abort(403, 'The Examination Module is not enabled for your institution.');
        }

        return $next($request);
    }
}
