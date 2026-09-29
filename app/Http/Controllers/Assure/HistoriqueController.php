<?php

namespace App\Http\Controllers\Assure;

use App\Http\Controllers\Controller;
use App\Services\AssureDashboardService;
use App\Services\HistoriqueService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoriqueController extends Controller
{
    public function index(Request $request, HistoriqueService $historique, AssureDashboardService $dashboard): Response
    {
        $assure = auth('assure')->user();
        $cat = $request->string('cat')->toString() ?: 'Tous';

        $events = collect($historique->eventsFor($assure));

        if ($cat !== 'Tous') {
            $events = $events->where('cat', $cat);
        }

        return Inertia::render('Assure/Historique/Index', [
            'events' => $events->values()->all(),
            'filter' => $cat,
            'categories' => ['Tous', 'Compte', 'Dossiers', 'Membres'],
            'unreadCount' => $dashboard->stats($assure)['unreadCount'],
        ]);
    }
}
