<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingsService;
use Inertia\Inertia;
use Inertia\Response;

class FooterController extends Controller
{
    public function index(PlatformSettingsService $settings): Response
    {
        return Inertia::render('Admin/Cms/Footer/Index', [
            'footer' => $settings->footer(),
        ]);
    }
}
