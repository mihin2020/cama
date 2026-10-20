<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Support\AdminPermissions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function __construct(
        private AdminInvitationService $invitation,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return AdminUser::query()
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->map(fn (AdminUser $user) => $this->format($user))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: AdminUser, invitationSent: bool}
     */
    public function create(array $data, AdminUser $actor): array
    {
        $role = AdminRole::from($data['role']);

        $this->assertCanManageRole($actor, $role);
        $permissions = $this->grantablePermissions($actor, $role, $data['permissions'] ?? []);

        $user = AdminUser::query()->create([
            'nom' => strtoupper(trim($data['nom'])),
            'prenom' => trim($data['prenom']),
            'grade' => $data['grade'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make(Str::password(24)),
            'role' => $role,
            'permissions' => $permissions,
            'matricule_interne' => $data['matricule_interne'] ?? null,
            'actif' => true,
            'invited_at' => now(),
        ]);

        $invitationSent = $this->invitation->send($user, $actor);

        return [
            'user' => $user,
            'invitationSent' => $invitationSent,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AdminUser $user, array $data, AdminUser $actor): AdminUser
    {
        $this->assertCanManage($actor, $user);

        $role = AdminRole::from($data['role']);
        $this->assertCanManageRole($actor, $role);
        $permissions = $this->grantablePermissions($actor, $role, $data['permissions'] ?? [], $user);

        $user->fill([
            'nom' => strtoupper(trim($data['nom'])),
            'prenom' => trim($data['prenom']),
            'grade' => $data['grade'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'role' => $role,
            'permissions' => $permissions,
            'matricule_interne' => $data['matricule_interne'] ?? $user->matricule_interne,
        ])->save();

        return $user->fresh();
    }

    public function toggleActif(AdminUser $user, AdminUser $actor): AdminUser
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'actif' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $this->assertCanManage($actor, $user);

        $attributes = ['actif' => ! $user->actif];

        if ($user->actif) {
            // Désactivation : on révoque aussi les cookies « Se souvenir de moi » du compte.
            $attributes['remember_token'] = Str::random(60);
        }

        $user->forceFill($attributes)->save();

        return $user->fresh();
    }

    public function resendInvitation(AdminUser $user, AdminUser $actor): bool
    {
        $this->assertCanManage($actor, $user);

        if ($user->last_login_at) {
            throw ValidationException::withMessages([
                'email' => 'Ce compte a déjà été activé.',
            ]);
        }

        $user->update([
            'invited_at' => now(),
            'actif' => true,
        ]);

        $sent = $this->invitation->send($user->fresh(), $actor);

        return $sent;
    }

    public function delete(AdminUser $user, AdminUser $actor): void
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'delete' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        $this->assertCanManage($actor, $user);

        if ($user->role === AdminRole::Administrateur) {
            $adminCount = AdminUser::query()
                ->where('role', AdminRole::Administrateur)
                ->count();

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'delete' => 'Impossible de supprimer le dernier administrateur.',
                ]);
            }
        }

        $user->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function format(AdminUser $user): array
    {
        return [
            'id' => $user->id,
            'grade' => $user->grade,
            'prenom' => $user->prenom,
            'nom' => $user->nom,
            'email' => $user->email,
            'role' => $user->role->value,
            'roleLabel' => $this->roleBadgeLabel($user->role),
            'statut' => $user->statut_label,
            'actif' => $user->actif,
            'permissions' => $user->effective_permissions,
            'permissionsCount' => count($user->effective_permissions),
            'displayName' => $user->display_name_with_grade,
            'initiales' => $user->initiales,
            'lastLoginAt' => $user->last_login_at?->format('d/m/Y H:i'),
            'canManage' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Comptes internes paginés, avec ce que le compte connecté peut faire sur chacun.
     */
    public function paginateForActor(AdminUser $actor, ?string $search, int $perPage): LengthAwarePaginator
    {
        return AdminUser::query()
            ->when($search, function ($query, string $search) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q->where('nom', 'like', $like)
                    ->orWhere('prenom', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AdminUser $user) => [
                ...$this->format($user),
                'canManage' => $this->canManage($actor, $user),
                'canResendInvitation' => $this->canManage($actor, $user) && ! $user->last_login_at,
                'canDelete' => $this->canManage($actor, $user) && $user->id !== $actor->id,
            ]);
    }

    public function canManage(AdminUser $actor, AdminUser $target): bool
    {
        if ($actor->role === AdminRole::Administrateur) {
            return true;
        }

        if ($actor->role === AdminRole::Superviseur) {
            return ! in_array($target->role, [AdminRole::Administrateur, AdminRole::Direction], true);
        }

        return false;
    }

    private function assertCanManage(AdminUser $actor, AdminUser $target): void
    {
        if (! $this->canManage($actor, $target)) {
            abort(403, 'Vous ne pouvez pas modifier ce compte interne.');
        }
    }

    /**
     * Permissions que le compte connecté peut attribuer (pour les cases de la fiche).
     *
     * @return list<string>
     */
    public function grantableBy(AdminUser $actor): array
    {
        return AdminPermissions::grantableBy($actor->role, app(AdminPermissionService::class)->expanded($actor));
    }

    /**
     * Contrôle des permissions demandées, contre l'élévation de privilèges :
     *  - chaque permission doit être compatible avec le rôle (ex. gestion des comptes) ;
     *  - un non-administrateur ne peut AJOUTER que des permissions qu'il possède lui-même
     *    (celles que le compte avait déjà sont conservées, pour pouvoir le modifier) ;
     *  - un non-administrateur ne peut pas changer ses propres droits ni son rôle.
     *
     * @param  array<int, mixed>  $requested
     * @return list<string>
     */
    private function grantablePermissions(AdminUser $actor, AdminRole $role, array $requested, ?AdminUser $target = null): array
    {
        $permissions = AdminPermissions::sanitize($requested);
        $current = $target?->effective_permissions ?? [];

        foreach ($permissions as $key) {
            if (! AdminPermissions::allowedForRole($key, $role)) {
                throw ValidationException::withMessages([
                    'permissions' => '« '.AdminPermissions::label($key).' » est réservé aux superviseurs et administrateurs.',
                ]);
            }
        }

        if ($actor->role === AdminRole::Administrateur) {
            return $permissions;
        }

        if ($target && $target->id === $actor->id) {
            $unchanged = $role === $target->role
                && collect($permissions)->sort()->values()->all() === collect($current)->sort()->values()->all();
            if (! $unchanged) {
                throw ValidationException::withMessages([
                    'permissions' => 'Vous ne pouvez pas modifier vos propres droits : demandez à un administrateur.',
                ]);
            }

            return $permissions;
        }

        $refused = array_diff($permissions, $this->grantableBy($actor), $current);
        if ($refused !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'Vous ne pouvez attribuer que des permissions que vous possédez vous-même. Non autorisé : '
                    .implode(', ', array_map(fn (string $key) => '« '.AdminPermissions::label($key).' »', $refused)).'.',
            ]);
        }

        return $permissions;
    }

    private function assertCanManageRole(AdminUser $actor, AdminRole $role): void
    {
        if ($actor->role === AdminRole::Administrateur) {
            return;
        }

        if ($actor->role === AdminRole::Superviseur && ! in_array($role, [AdminRole::Gestionnaire, AdminRole::Superviseur], true)) {
            throw ValidationException::withMessages([
                'role' => 'Seul un administrateur technique peut créer ou promouvoir un administrateur.',
            ]);
        }

        if ($actor->role !== AdminRole::Superviseur && $actor->role !== AdminRole::Administrateur) {
            abort(403, 'Accès non autorisé.');
        }
    }

    private function roleBadgeLabel(AdminRole $role): string
    {
        return match ($role) {
            AdminRole::Gestionnaire => 'Gestionnaire',
            AdminRole::Superviseur => 'Superviseur',
            AdminRole::Administrateur => 'Administrateur',
            AdminRole::Direction => 'Direction',
        };
    }
}
