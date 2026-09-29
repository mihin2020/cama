<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AdminUser;

class AdminNotificationService
{
    public function listFor(AdminUser $admin): array
    {
        return AdminNotification::query()
            ->where('admin_user_id', $admin->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AdminNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'titre' => $n->titre,
                'contenu' => $n->contenu,
                'lien' => $n->lien,
                'lu' => $n->lu,
                'date' => $n->created_at->format('d/m/Y H:i'),
            ])
            ->all();
    }

    public function unreadCount(AdminUser $admin): int
    {
        return AdminNotification::query()
            ->where('admin_user_id', $admin->id)
            ->where('lu', false)
            ->count();
    }

    public function markRead(AdminNotification $notification, AdminUser $admin): void
    {
        if ($notification->admin_user_id !== $admin->id) {
            abort(403);
        }

        $notification->update(['lu' => true]);
    }

    public function markAllRead(AdminUser $admin): void
    {
        AdminNotification::query()
            ->where('admin_user_id', $admin->id)
            ->where('lu', false)
            ->update(['lu' => true]);
    }

    public function seedForUser(AdminUser $admin): void
    {
        if (AdminNotification::query()->where('admin_user_id', $admin->id)->exists()) {
            return;
        }

        $samples = [
            [
                'type' => 'soumission',
                'titre' => 'Nouvelle soumission',
                'contenu' => 'Fatoumata TRAORÉ — dossier CAMA-2026-15302 soumis par l\'assuré.',
                'lien' => route('admin.dossiers'),
                'lu' => false,
            ],
            [
                'type' => 'retard',
                'titre' => 'Dossier en retard',
                'contenu' => 'CAMA-2025-91007 dépasse le délai moyen de traitement.',
                'lien' => route('admin.dossiers'),
                'lu' => false,
            ],
            [
                'type' => 'compte',
                'titre' => 'Nouveau compte assuré',
                'contenu' => 'David KONÉ a créé un compte, en attente de validation.',
                'lien' => route('admin.assures'),
                'lu' => false,
            ],
            [
                'type' => 'export',
                'titre' => 'Export terminé',
                'contenu' => 'Export CSV du journal d\'audit (01/05 → 31/05) prêt.',
                'lien' => route('admin.exports'),
                'lu' => true,
            ],
            [
                'type' => 'securite',
                'titre' => 'Alerte de sécurité',
                'contenu' => 'Tentative de connexion échouée (x3) sur le compte INT-0260.',
                'lien' => null,
                'lu' => true,
            ],
        ];

        foreach ($samples as $sample) {
            AdminNotification::query()->create([
                'admin_user_id' => $admin->id,
                ...$sample,
            ]);
        }
    }

    public function notifyAllAdmins(string $type, string $titre, string $contenu, ?string $lien = null, array $roles = []): void
    {
        $query = AdminUser::query()->where('actif', true);
        if ($roles) {
            $query->whereIn('role', $roles);
        }

        $permissionService = app(AdminPermissionService::class);

        $query->get()
            ->filter(fn (AdminUser $admin) => $permissionService->allows($admin, 'notifications.receive'))
            ->each(fn (AdminUser $admin) => AdminNotification::query()->create([
                'admin_user_id' => $admin->id,
                'type' => $type,
                'titre' => $titre,
                'contenu' => $contenu,
                'lien' => $lien,
                'lu' => false,
            ]));
    }
}
