<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Services\CmsActivityLogService;
use App\Services\CmsDashboardService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, CmsDashboardService $dashboard, CmsActivityLogService $activity): Response|RedirectResponse
    {
        $paginator = $activity->paginate(Pagination::perPage($request, Pagination::FEED));

        // Page demandée au-delà de la dernière (journal purgé, lien ancien) : retour à la dernière page.
        if (Pagination::isBeyondLastPage($paginator)) {
            return redirect()->route('admin.cms.dashboard', [...$request->query(), 'page' => $paginator->lastPage()]);
        }

        return Inertia::render('Admin/Cms/Dashboard', [
            'stats' => $dashboard->stats(),
            'activity' => $paginator->items(),
            'pagination' => Pagination::meta($paginator, Pagination::FEED),
        ]);
    }
}
