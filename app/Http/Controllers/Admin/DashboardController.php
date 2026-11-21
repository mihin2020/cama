<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboard): Response|RedirectResponse
    {
        $admin = auth('admin')->user();

        // Sans accès aux dossiers (ex. webmaster), le compte arrive directement sur son premier module.
        if (! AdminAccess::seesDashboard($admin)) {
            return redirect()->route(AdminAccess::landingRoute($admin));
        }

        // Sans « Consulter tous les dossiers », le tableau de bord se limite aux dossiers de l'agent.
        $filtre = $admin?->seesOnlyOwnDossiers() ? $admin->display_name : null;

        return Inertia::render('Admin/Dashboard', [
            'stats' => $dashboard->stats($filtre),
            'vuePersonnelle' => $filtre !== null,
        ]);
    }
}
