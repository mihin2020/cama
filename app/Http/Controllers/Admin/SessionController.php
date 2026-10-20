<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceAdminIdleTimeout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Envoyé par le front quand l'admin interagit avec la page sans changer de page :
     * le middleware admin.idle a déjà repoussé l'échéance, on renvoie le nouvel état.
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json(EnforceAdminIdleTimeout::clientState($request));
    }
}
