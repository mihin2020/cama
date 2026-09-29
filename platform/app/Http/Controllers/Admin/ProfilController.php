<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminProfilService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfilController extends Controller
{
    public function show(AdminProfilService $service): Response
    {
        $admin = auth('admin')->user();

        return Inertia::render('Admin/Profil/Show', [
            'profile' => $service->formatProfile($admin),
        ]);
    }

    public function updatePassword(Request $request, AdminProfilService $service): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $service->updatePassword(
            auth('admin')->user(),
            $data['current_password'],
            $data['password'],
        );

        return back()->with('success', 'Mot de passe mis à jour.');
    }
}
