<?php

namespace App\Http\Controllers\Assure;

use App\Http\Controllers\Controller;
use App\Models\AssureNotification;
use App\Services\AssureDashboardService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, AssureDashboardService $dashboard): Response|RedirectResponse
    {
        $assure = auth('assure')->user();

        $notifications = $assure->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Pagination::perPage($request, Pagination::COMPACT))
            ->withQueryString()
            ->through(fn (AssureNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'titre' => $n->titre,
                'contenu' => $n->contenu,
                'lien' => $n->lien,
                'lu' => $n->lu,
                'date' => $n->created_at->format('d/m/Y H:i'),
            ]);

        if (Pagination::isBeyondLastPage($notifications)) {
            return redirect()->route('assure.notifications', [...$request->query(), 'page' => $notifications->lastPage()]);
        }

        return Inertia::render('Assure/Notifications/Index', [
            'notifications' => $notifications->items(),
            'pagination' => Pagination::meta($notifications, Pagination::COMPACT),
            'unreadCount' => $dashboard->stats($assure)['unreadCount'],
        ]);
    }

    public function markRead(AssureNotification $notification): RedirectResponse
    {
        $assure = auth('assure')->user();
        abort_unless($notification->assure_id === $assure->id, 403);

        $notification->update(['lu' => true]);

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        $assure = auth('assure')->user();
        $assure->notifications()->where('lu', false)->update(['lu' => true]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
