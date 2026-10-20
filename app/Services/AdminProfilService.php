<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Dossier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminProfilService
{
    public function __construct(private AdminDossierService $dossiers) {}

    public function formatProfile(AdminUser $admin): array
    {
        return [
            'displayName' => $admin->display_name,
            'roleLabel' => $admin->role->label(),
            'matriculeInterne' => $admin->matricule_interne ?? '—',
            'email' => $admin->email,
            'lastLogin' => $admin->last_login_at?->format('d/m/Y H:i') ?? '—',
            'initiales' => $admin->initiales,
            'deuxFa' => (bool) $admin->deux_fa_active,
            'deconnexionAuto' => (bool) $admin->deconnexion_auto_active,
            'isGestionnaire' => $admin->role === AdminRole::Gestionnaire,
            'workload' => $admin->role === AdminRole::Gestionnaire
                ? $this->gestionnaireWorkload($admin)
                : null,
        ];
    }

    public function updatePassword(AdminUser $admin, string $current, string $new): void
    {
        if (! Hash::check($current, $admin->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }

        if (strlen($new) < 12) {
            throw ValidationException::withMessages(['password' => 'Le mot de passe doit contenir au moins 12 caractères.']);
        }

        // Nouveau jeton : tous les cookies « Se souvenir de moi » déjà émis deviennent invalides.
        $admin->forceFill([
            'password' => $new,
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function setTwoFactor(AdminUser $admin, bool $active): void
    {
        $admin->forceFill([
            'deux_fa_active' => $active,
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
            'login_2fa_attempts' => 0,
        ])->save();

        Log::info($active ? '2FA admin activée' : '2FA admin désactivée', ['admin_id' => $admin->id]);
    }

    public function setIdleLogout(AdminUser $admin, bool $active): void
    {
        $admin->forceFill(['deconnexion_auto_active' => $active])->save();

        Log::info($active ? 'Déconnexion auto admin activée' : 'Déconnexion auto admin désactivée', ['admin_id' => $admin->id]);
    }

    private function gestionnaireWorkload(AdminUser $admin): array
    {
        $dossiers = Dossier::query()
            ->where('gestionnaire', $admin->display_name)
            ->where('statut', '!=', 'Brouillon')
            ->get();

        $openStatuts = ['Soumis', 'En instruction', 'Pièce manquante demandée', 'En attente supervision'];
        $nonTraites = $dossiers->whereIn('statut', $openStatuts)->count();
        $valides = $dossiers->where('statut', 'Validé')->count();

        $retard = $dossiers->filter(function (Dossier $d) use ($openStatuts) {
            if (! in_array($d->statut, $openStatuts, true) || ! $d->date_soumission) {
                return false;
            }

            return $d->date_soumission->diffInDays(now()) > 14;
        })->count();

        return [
            'assignes' => $dossiers->count(),
            'nonTraites' => $nonTraites,
            'valides' => $valides,
            'retard' => $retard,
        ];
    }
}
