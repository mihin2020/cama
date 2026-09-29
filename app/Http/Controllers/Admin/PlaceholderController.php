<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceholderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/Placeholder', [
            'module' => $request->route('module', 'Module'),
            'activeNav' => $request->route('nav', 'dashboard'),
        ]);
    }
}
