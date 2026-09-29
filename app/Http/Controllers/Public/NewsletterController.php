<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicNewsletterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request, PublicNewsletterService $newsletter): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:160'],
        ]);

        $newsletter->subscribe($data['email'], $data['name'] ?? null, $request);

        return back()->with('success', 'Inscription newsletter confirmée.');
    }

    public function unsubscribe(string $token, PublicNewsletterService $newsletter): RedirectResponse
    {
        $ok = $newsletter->unsubscribeByToken($token);

        return redirect('/')
            ->with($ok ? 'success' : 'error', $ok ? 'Vous êtes désinscrit de la newsletter.' : 'Lien de désinscription invalide.');
    }
}
