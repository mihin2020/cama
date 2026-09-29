<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAssureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('assure')->check()) {
            return redirect()->route('assure.dashboard');
        }

        return $next($request);
    }
}
