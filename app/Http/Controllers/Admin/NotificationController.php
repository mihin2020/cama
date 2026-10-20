<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, AdminNotificationService $service): Response|RedirectResponse
    {
        $admin = auth('admin')->user();
        $paginator = $service->paginateFor($admin, Pagination::perPage($request, Pagination::FEED));

        if (Pagination::isBeyondLastPage($paginator)) {
            return redirect()->route('admin.notifications', [...$request->query(), 'page' => $paginator->lastPage()]);
        }

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $paginator->items(),
            'pagination' => Pagination::meta($paginator, Pagination::FEED),
            'unreadCount' => $service->unreadCount($admin),
        ]);
    }

    public function markRead(AdminNotification $notification, AdminNotificationService $service): RedirectResponse
    {
        $service->markRead($notification, auth('admin')->user());

        if ($notification->lien) {
            return redirect($notification->lien);
        }

        return back();
    }

    public function markAllRead(AdminNotificationService $service): RedirectResponse
    {
        $service->markAllRead(auth('admin')->user());

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
