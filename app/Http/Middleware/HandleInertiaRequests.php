<?php

namespace App\Http\Middleware;

use App\Enums\AssureStatut;
use App\Models\Assure;
use App\Models\CmsContactMessage;
use App\Models\CmsNewsletterSubscriber;
use App\Services\AdminDossierService;
use App\Services\AdminNotificationService;
use App\Services\PublicSiteService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $assure = $request->user('assure');
        $admin = $request->user('admin');

        return [
            ...parent::share($request),
            'auth' => [
                'assure' => $assure ? [
                    'id' => $assure->id,
                    'fullName' => $assure->full_name,
                    'email' => $assure->email,
                    'matricule' => $assure->matricule,
                    'numeroCama' => $assure->numero_cama,
                    'initiales' => $assure->initiales,
                    'statut' => $assure->statut->value,
                    'statutLabel' => $assure->statut->label(),
                    'peutEnroler' => $assure->peutEnroler(),
                ] : null,
                'admin' => $admin ? [
                    'id' => $admin->id,
                    'fullName' => $admin->full_name,
                    'displayName' => $admin->display_name,
                    'email' => $admin->email,
                    'role' => $admin->role->value,
                    'roleLabel' => $admin->role->label(),
                    'initiales' => $admin->initiales,
                    'permissions' => $admin->effective_permissions,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
                'famille_submitted' => fn () => $request->session()->get('famille_submitted'),
                'dev_2fa_code' => fn () => $request->session()->get('dev_2fa_code'),
            ],
            'app' => [
                'name' => config('app.name'),
                'inscriptionsEnAttente' => fn () => Assure::query()
                    ->where('statut', AssureStatut::EnAttenteValidation)
                    ->count(),
                'adminUnreadNotifications' => fn () => $admin
                    ? app(AdminNotificationService::class)->unreadCount($admin)
                    : 0,
                'cmsNewContactCount' => fn () => $admin
                    ? CmsContactMessage::query()
                        ->whereNull('archived_at')
                        ->where('status', 'new')
                        ->count()
                    : 0,
                'cmsActiveNewsletterCount' => fn () => $admin
                    ? CmsNewsletterSubscriber::query()->whereNull('unsubscribed_at')->count()
                    : 0,
                'adminPendingDossierFamilies' => fn () => $admin
                    ? app(AdminDossierService::class)->pendingFamilyAssureIds($admin)
                    : [],
                'assureUnreadNotifications' => fn () => $assure
                    ? $assure->notifications()->where('lu', false)->count()
                    : 0,
                'assurePreviewNotifications' => fn () => $assure
                    ? $assure->notifications()
                        ->orderByDesc('created_at')
                        ->take(5)
                        ->get()
                        ->map(fn ($n) => [
                            'id' => $n->id,
                            'type' => $n->type,
                            'titre' => $n->titre,
                            'contenu' => $n->contenu,
                            'lien' => $n->lien,
                            'lu' => $n->lu,
                            'date' => $n->created_at->format('d/m/Y H:i'),
                        ])
                        ->all()
                    : [],
                'googleMapsApiKey' => config('services.google.maps_key'),
            ],
            'publicSite' => fn () => app(PublicSiteService::class)->shared(),
        ];
    }
}
