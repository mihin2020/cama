<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rôles des comptes internes (ex. « Webmaster »).
 *
 * Règles :
 *  - le rôle Administrateur garde toujours toutes les permissions et ne peut être ni désactivé,
 *    ni supprimé ; seuls son nom et sa description se modifient ;
 *  - les comptes qui « suivent » leur rôle (aucune permission propre) reçoivent aussitôt les
 *    modifications du rôle ; les comptes personnalisés gardent leurs cases ;
 *  - supprimer un rôle d'origine exige le mot de passe de l'administrateur ; le rôle
 *    Administrateur, lui, ne se supprime jamais (sinon plus personne ne gère la plateforme) ;
 *  - un rôle créé est au niveau d'un gestionnaire : superviseurs et administrateurs gèrent ses
 *    comptes, et il ne peut pas recevoir « Gérer les comptes internes » ;
 *  - un rôle encore attribué à des comptes ne se supprime pas.
 */
class AdminRoleService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return Role::query()
            ->withCount(['users', 'users as custom_users_count' => fn ($query) => $query->whereNotNull('permissions')])
            ->orderByDesc('rang')
            ->orderBy('nom')
            ->get()
            ->map(fn (Role $role) => $this->format($role))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function format(Role $role): array
    {
        return [
            'id' => $role->id,
            'slug' => $role->slug,
            'nom' => $role->nom,
            'description' => $role->description,
            'rang' => $role->rang,
            'systeme' => $role->systeme,
            'actif' => $role->actif,
            'administrateur' => $role->isAdministrateur(),
            'permissions' => AdminPermissions::expand($role->permissionKeys()),
            'usersCount' => (int) ($role->users_count ?? $role->users()->count()),
            // Comptes de ce rôle aux permissions personnalisées (non mis à jour par le rôle).
            'customUsersCount' => (int) ($role->custom_users_count ?? $role->users()->whereNotNull('permissions')->count()),
        ];
    }

    /**
     * @param  array{nom: string, description?: ?string, permissions?: array<int, string>}  $data
     */
    public function create(array $data, AdminUser $actor): Role
    {
        $rang = Role::RANG_PERSONNALISE;

        return Role::query()->create([
            'slug' => $this->uniqueSlug($data['nom']),
            'nom' => trim($data['nom']),
            'description' => $this->description($data),
            'rang' => $rang,
            'systeme' => false,
            'actif' => true,
            'permissions' => $this->checkedPermissions($data['permissions'] ?? [], $rang, $actor),
        ]);
    }

    /**
     * @param  array{nom: string, description?: ?string, permissions?: array<int, string>, apply_to_users?: bool}  $data
     * @return int Nombre de comptes personnalisés ramenés sur les permissions du rôle.
     */
    public function update(Role $role, array $data, AdminUser $actor): int
    {
        $attributes = [
            'nom' => trim($data['nom']),
            'description' => $this->description($data),
        ];

        if (! $role->isAdministrateur()) {
            $attributes['permissions'] = $this->checkedPermissions($data['permissions'] ?? [], $role->rang, $actor);
        }

        return DB::transaction(function () use ($role, $attributes, $data) {
            $role->update($attributes);

            if (empty($data['apply_to_users']) || $role->isAdministrateur()) {
                return 0;
            }

            // Les comptes personnalisés suivent de nouveau le rôle (les autres le suivent déjà).
            return $role->users()->whereNotNull('permissions')->update(['permissions' => null]);
        });
    }

    public function toggle(Role $role, AdminUser $actor): Role
    {
        if ($role->isAdministrateur()) {
            throw ValidationException::withMessages(['role' => 'Le rôle Administrateur ne peut pas être désactivé.']);
        }

        if ($role->slug === $actor->role) {
            throw ValidationException::withMessages(['role' => 'Vous ne pouvez pas désactiver votre propre rôle.']);
        }

        $role->update(['actif' => ! $role->actif]);

        return $role;
    }

    public function delete(Role $role, AdminUser $actor, ?string $password = null): void
    {
        if ($role->isAdministrateur()) {
            throw ValidationException::withMessages(['role' => 'Le rôle Administrateur ne peut pas être supprimé : sans lui, plus personne ne pourrait gérer la plateforme.']);
        }

        // Rôle d'origine : on confirme l'identité de l'administrateur avec son mot de passe actuel.
        if ($role->systeme && ! Hash::check((string) $password, $actor->password)) {
            throw ValidationException::withMessages(['password' => 'Mot de passe incorrect.']);
        }

        $count = $role->users()->count();
        if ($count > 0) {
            throw ValidationException::withMessages(['role' => "Le rôle « {$role->nom} » est attribué à {$count} compte(s) : changez d'abord leur rôle."]);
        }

        $role->delete();
    }

    /**
     * @param  array<int, mixed>  $requested
     * @return list<string>
     */
    private function checkedPermissions(array $requested, int $rang, AdminUser $actor): array
    {
        $permissions = AdminPermissions::sanitize($requested);

        $tooHigh = array_filter($permissions, fn (string $key) => ! AdminPermissions::allowedForRank($key, $rang));
        if ($tooHigh !== []) {
            throw ValidationException::withMessages([
                'permissions' => implode(' ', array_map(
                    fn (string $key) => '« '.AdminPermissions::label($key).' » : '.AdminPermissions::restrictionLabel($key),
                    $tooHigh,
                )),
            ]);
        }

        // Pas d'élévation de privilèges : on ne met dans un rôle que ce qu'on peut soi-même attribuer.
        $grantable = AdminPermissions::grantableBy($actor->isAdministrateur(), app(AdminPermissionService::class)->expanded($actor));
        $refused = array_diff($permissions, $grantable);
        if ($refused !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'Vous ne pouvez mettre dans un rôle que des permissions que vous possédez.',
            ]);
        }

        return array_values($permissions);
    }

    private function description(array $data): ?string
    {
        $description = trim((string) ($data['description'] ?? ''));

        return $description === '' ? null : $description;
    }

    private function uniqueSlug(string $nom): string
    {
        $base = Str::slug($nom) ?: 'role';
        $base = Str::limit($base, 50, '');
        $slug = $base;
        $i = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
