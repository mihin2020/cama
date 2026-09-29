<?php

namespace App\Http\Controllers;

use App\Services\PublicSiteService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(PublicSiteService $site): Response
    {
        return Inertia::render('Public/Home', $site->home());
    }
}
