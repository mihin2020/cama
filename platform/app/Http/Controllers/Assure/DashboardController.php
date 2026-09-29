<?php

namespace App\Http\Controllers\Assure;

use App\Http\Controllers\Controller;
use App\Services\AssureDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AssureDashboardService $dashboard): Response
    {
        $assure = auth('assure')->user();
        $data = $dashboard->stats($assure);

        return Inertia::render('Assure/Dashboard', [
            'assure' => [
                'fullName' => $assure->full_name,
                'prenom' => $assure->prenom,
                'nom' => $assure->nom,
                'matricule' => $assure->matricule,
                'numeroCama' => $assure->numero_cama,
                'numeroCim' => $assure->numero_cim,
                'statut' => $assure->statut->label(),
                'statutValue' => $assure->statut->value,
                'peutEnroler' => $assure->peutEnroler(),
            ],
            ...$data,
        ]);
    }
}
