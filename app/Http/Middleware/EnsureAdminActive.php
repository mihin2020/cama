<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe immédiatement l'accès d'un compte désactivé (ou dont le rôle a été désactivé, ou qui n'a
 * plus aucune permission), y compris s'il revient avec une session encore ouverte ou un cookie
 * « Se souvenir de moi ».
 */
class EnsureAdminActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin && ($refusal = AdminAccess::refusal($admin))) {
            Auth::guard('admin')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', $refusal);
        }

        return $next($request);
    }
}
