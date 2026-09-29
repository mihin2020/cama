<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(AdminNotificationService $service): Response
    {
        $admin = auth('admin')->user();

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $service->listFor($admin),
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
