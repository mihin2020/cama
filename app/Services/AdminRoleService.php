<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rôles des comptes internes (ex. « Webmaster »).
 *
 * Règles :
 *  - le rôle Administrateur garde toujours toutes les permissions et ne peut être ni désactivé,
 *    ni supprimé ; seuls son nom et sa description se modifient ;
 *  - les rôles système (les 4 rôles d'origine) gardent leur rang et ne se suppriment pas ;
 *  - un rôle personnalisé a un rang de 1 à 99 (toujours sous l'administrateur) ;
 *  - un rôle encore attribué à des comptes ne se supprime pas ;
 *  - les permissions d'un rôle respectent son rang (ex. gérer les comptes : rang 50 minimum).
 */
class AdminRoleService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return Role::query()
            ->withCount('users')
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
            'permissions' => $role->permissionKeys(),
            'usersCount' => (int) ($role->users_count ?? $role->users()->count()),
        ];
    }

    /**
     * @param  array{nom: string, description?: ?string, rang: int, permissions?: array<int, string>}  $data
     */
    public function create(array $data, AdminUser $actor): Role
    {
        $rang = (int) $data['rang'];

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
     * @param  array{nom: string, description?: ?string, rang?: int, permissions?: array<int, string>, apply_to_users?: bool}  $data
     * @return int Nombre de comptes dont les permissions ont été réalignées sur le rôle.
     */
    public function update(Role $role, array $data, AdminUser $actor): int
    {
        $attributes = [
            'nom' => trim($data['nom']),
            'description' => $this->description($data),
        ];

        if (! $role->isAdministrateur()) {
            $rang = $role->systeme ? $role->rang : (int) ($data['rang'] ?? $role->rang);
            $attributes['rang'] = $rang;
            $attributes['permissions'] = $this->checkedPermissions($data['permissions'] ?? [], $rang, $actor);
        }

        return DB::transaction(function () use ($role, $attributes, $data) {
            $role->update($attributes);

            if (empty($data['apply_to_users']) || $role->isAdministrateur()) {
                return 0;
            }

            // Les comptes du rôle reprennent exactement ses permissions.
            return $role->users()->update(['permissions' => json_encode($role->fresh()->permissionKeys())]);
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

    public function delete(Role $role): void
    {
        if ($role->systeme) {
            throw ValidationException::withMessages(['role' => "Le rôle « {$role->nom} » fait partie des rôles d'origine : il ne peut pas être supprimé (vous pouvez le désactiver)."]);
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
                'permissions' => 'Rang trop bas pour : '.implode(', ', array_map(
                    fn (string $key) => '« '.AdminPermissions::label($key).' » (rang '.AdminPermissions::MIN_RANK[$key].' minimum)',
                    $tooHigh,
                )).'.',
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
