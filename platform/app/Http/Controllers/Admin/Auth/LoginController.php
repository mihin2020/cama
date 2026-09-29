<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, AdminAuditService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        $request->session()->regenerate();

        /** @var \App\Models\AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->actif) {
            Auth::guard('admin')->logout();
            throw ValidationException::withMessages([
                'email' => 'Ce compte administrateur est désactivé.',
            ]);
        }

        $admin->update(['last_login_at' => now()]);

        $audit->logConnexion($admin, $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request, AdminAuditService $audit): RedirectResponse
    {
        /** @var \App\Models\AdminUser|null $admin */
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            $audit->logDeconnexion($admin, $request);
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
