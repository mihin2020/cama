<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboard): Response
    {
        $admin = auth('admin')->user();

        // Un gestionnaire voit un tableau de bord restreint à ses propres dossiers.
        // Les rôles d'encadrement (superviseur, administrateur, direction) gardent la vue globale.
        $filtre = $admin?->role === AdminRole::Gestionnaire ? $admin->display_name : null;

        return Inertia::render('Admin/Dashboard', [
            'stats' => $dashboard->stats($filtre),
            'vuePersonnelle' => $filtre !== null,
        ]);
    }
}
