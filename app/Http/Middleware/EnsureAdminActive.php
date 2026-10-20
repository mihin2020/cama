<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe immédiatement l'accès d'un compte désactivé, y compris s'il revient
 * avec une session encore ouverte ou un cookie « Se souvenir de moi ».
 */
class EnsureAdminActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin && ! $admin->actif) {
            Auth::guard('admin')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Ce compte administrateur est désactivé.');
        }

        return $next($request);
    }
}
