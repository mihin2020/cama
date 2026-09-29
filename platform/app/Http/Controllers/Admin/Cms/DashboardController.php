<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Services\CmsActivityLogService;
use App\Services\CmsDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(CmsDashboardService $dashboard, CmsActivityLogService $activity): Response
    {
        return Inertia::render('Admin/Cms/Dashboard', [
            'stats' => $dashboard->stats(),
            'activity' => $activity->recent(),
        ]);
    }
}
