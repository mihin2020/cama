<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $assure = $request->user('assure');

        if ($assure && ! $assure->hasVerifiedEmail()) {
            return redirect()->route('assure.verification.notice');
        }

        return $next($request);
    }
}
