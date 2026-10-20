<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\AdminLogin2faService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function create(Request $request, AdminLogin2faService $service): Response|RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        return Inertia::render('Admin/Auth/Login', [
            'step' => '2fa',
            'maskedEmail' => $service->maskEmail($admin->email),
            'codeLength' => AdminLogin2faService::CODE_LENGTH,
        ]);
    }

    public function store(Request $request, AdminLogin2faService $service): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $length = AdminLogin2faService::CODE_LENGTH;

        $data = $request->validate([
            'code' => ['required', 'digits:'.$length],
        ], [
            'code.digits' => "Le code comporte {$length} chiffres.",
        ]);

        $service->verify($admin, $data['code']);

        $remember = (bool) $request->session()->pull('admin_2fa_remember', false);
        $request->session()->forget('admin_2fa_pending');

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function resend(Request $request, AdminLogin2faService $service): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $devCode = $service->sendCode($admin);

        return redirect()->route('admin.login.2fa')
            ->with('success', "Un nouveau code a été envoyé à {$service->maskEmail($admin->email)}.")
            ->with('dev_2fa_code', $devCode);
    }

    public function destroy(Request $request, AdminLogin2faService $service): RedirectResponse
    {
        if ($admin = $this->pendingAdmin($request)) {
            $service->clearCode($admin);
        }

        $request->session()->forget(['admin_2fa_pending', 'admin_2fa_remember']);

        return redirect()->route('admin.login');
    }

    private function pendingAdmin(Request $request): ?AdminUser
    {
        $id = $request->session()->get('admin_2fa_pending');

        if (! $id) {
            return null;
        }

        $admin = AdminUser::query()->find($id);

        // Compte supprimé ou désactivé entre les deux étapes.
        if (! $admin || ! $admin->actif) {
            $request->session()->forget(['admin_2fa_pending', 'admin_2fa_remember']);

            return null;
        }

        return $admin;
    }
}
