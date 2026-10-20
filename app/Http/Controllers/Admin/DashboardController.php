<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboard): Response
    {
        $admin = auth('admin')->user();

        // Sans « Consulter tous les dossiers », le tableau de bord se limite aux dossiers de l'agent.
        $filtre = $admin && ! $admin->hasPermission('dossiers.view_all') ? $admin->display_name : null;

        return Inertia::render('Admin/Dashboard', [
            'stats' => $dashboard->stats($filtre),
            'vuePersonnelle' => $filtre !== null,
        ]);
    }
}
