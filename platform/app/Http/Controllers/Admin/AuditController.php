<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request, AdminAuditService $audit): Response
    {
        $filters = $request->only(['action', 'admin_user_id']);

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $audit->paginate($filters),
            'users' => $audit->userFilterOptions(),
            'filters' => [
                'action' => $filters['action'] ?? '',
                'admin_user_id' => $filters['admin_user_id'] ?? '',
            ],
            'retentionDays' => 90,
        ]);
    }
}
