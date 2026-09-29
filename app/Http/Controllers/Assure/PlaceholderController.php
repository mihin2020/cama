<?php

namespace App\Http\Controllers\Assure;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceholderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Assure/Placeholder', [
            'module' => $request->route('module', 'Module'),
            'activeNav' => $request->route('nav', 'dashboard'),
        ]);
    }
}
