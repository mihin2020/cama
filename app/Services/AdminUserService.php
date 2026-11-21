<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Role;
use App\Support\AdminPermissions;
use App\Support\SqlLike;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
        $role = $this->findRole($data['role']);

        $this->assertCanManageRole($actor, $role);
        $permissions = $this->grantablePermissions($actor, $role, $data['permissions'] ?? []);

        $user = AdminUser::query()->create([
            'nom' => strtoupper(trim($data['nom'])),
            'prenom' => trim($data['prenom']),
            'grade' => $data['grade'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make(Str::password(24)),
            'role' => $role->slug,
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

        $role = $this->findRole($data['role']);
        $this->assertCanManageRole($actor, $role);
        $permissions = $this->grantablePermissions($actor, $role, $data['permissions'] ?? [], $user);

        $email = strtolower(trim($data['email']));
        $emailChanged = $email !== strtolower($user->email);
        if ($emailChanged) {
            $this->assertCanChangeEmail($actor, $user);
            // Un lien de réinitialisation demandé avant le changement ne doit pas survivre.
            Password::broker('admin_users')->deleteToken($user);
        }

        $user->fill([
            'nom' => strtoupper(trim($data['nom'])),
            'prenom' => trim($data['prenom']),
            'grade' => $data['grade'] ?? null,
            'email' => $email,
            'role' => $role->slug,
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

        if ($user->isAdministrateur()) {
            $adminCount = AdminUser::query()
                ->where('role', $user->role)
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
            'matriculeInterne' => $user->matricule_interne,
            'role' => $user->role,
            'roleLabel' => $user->role_label,
            'roleSysteme' => (bool) $user->roleRecord()?->systeme,
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
            ->with('roleDefinition')
            ->when($search, function ($query, string $search) {
                $like = SqlLike::contains($search);
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

    /**
     * Hiérarchie (interne, non affichée) : l'administrateur gère tous les comptes ; un superviseur
     * gère les gestionnaires, les superviseurs et les rôles créés, jamais la Direction ni un
     * administrateur.
     */
    public function canManage(AdminUser $actor, AdminUser $target): bool
    {
        if ($actor->isAdministrateur()) {
            return true;
        }

        if (! $actor->hasPermission('users.manage') || $target->isAdministrateur()) {
            return false;
        }

        return $target->role_rang <= $actor->role_rang;
    }

    /**
     * Rôles que le compte connecté peut attribuer (liste déroulante de la fiche).
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function assignableRoles(AdminUser $actor)
    {
        return Role::query()
            ->actifs()
            ->orderBy('rang')
            ->orderBy('nom')
            ->get()
            ->filter(fn (Role $role) => $this->canAssignRole($actor, $role))
            ->values();
    }

    public function canAssignRole(AdminUser $actor, Role $role): bool
    {
        if ($actor->isAdministrateur()) {
            return true;
        }

        return $actor->hasPermission('users.manage')
            && ! $role->isAdministrateur()
            && $role->rang <= $actor->role_rang;
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
        return AdminPermissions::grantableBy($actor->isAdministrateur(), app(AdminPermissionService::class)->expanded($actor));
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
    private function grantablePermissions(AdminUser $actor, Role $role, array $requested, ?AdminUser $target = null): array
    {
        $permissions = AdminPermissions::sanitize($requested);
        $current = $target?->effective_permissions ?? [];

        foreach ($permissions as $key) {
            if (! AdminPermissions::allowedForRole($key, $role)) {
                throw ValidationException::withMessages([
                    'permissions' => '« '.AdminPermissions::label($key).' » ne peut pas être donné au rôle « '.$role->nom.' » : '.lcfirst(AdminPermissions::restrictionLabel($key) ?? ''),
                ]);
            }
        }

        if ($actor->isAdministrateur()) {
            return $permissions;
        }

        if ($target && $target->id === $actor->id) {
            $unchanged = $role->slug === $target->role
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

    /**
     * L'adresse e-mail d'un compte déjà utilisé sert à récupérer son mot de passe : la changer
     * reviendrait à en prendre le contrôle (« mot de passe oublié »). Réservé à un administrateur,
     * jamais sur son propre compte. Un compte invité qui ne s'est jamais connecté reste corrigeable
     * (faute de frappe dans l'invitation).
     */
    private function assertCanChangeEmail(AdminUser $actor, AdminUser $target): void
    {
        if (! $target->last_login_at) {
            return;
        }

        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'email' => 'Vous ne pouvez pas modifier votre propre adresse e-mail : demandez à un autre administrateur.',
            ]);
        }

        if (! $actor->isAdministrateur()) {
            throw ValidationException::withMessages([
                'email' => "Seul un administrateur peut modifier l'adresse e-mail d'un compte déjà actif.",
            ]);
        }
    }

    private function findRole(string $slug): Role
    {
        $role = Role::query()->where('slug', $slug)->first();

        if (! $role || ! $role->actif) {
            throw ValidationException::withMessages(['role' => "Ce rôle n'existe pas ou est désactivé."]);
        }

        return $role;
    }

    private function assertCanManageRole(AdminUser $actor, Role $role): void
    {
        if (! $actor->isAdministrateur() && ! $actor->hasPermission('users.manage')) {
            abort(403, 'Accès non autorisé.');
        }

        if (! $this->canAssignRole($actor, $role)) {
            throw ValidationException::withMessages([
                'role' => $role->isAdministrateur()
                    ? 'Seul un administrateur technique peut créer ou promouvoir un administrateur.'
                    : "Seul un administrateur peut attribuer le rôle « {$role->nom} ».",
            ]);
        }
    }
}
