<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rôle d'un compte interne (table admin_roles).
 *
 * Les permissions du rôle servent de modèle : elles sont proposées à la création d'un compte,
 * appliquées aux comptes qui n'ont pas de permissions propres, et peuvent être réappliquées aux
 * comptes existants. Le rang fixe la hiérarchie : on ne gère que les comptes d'un rang inférieur
 * ou égal au sien (l'administrateur gère tout).
 */
class Role extends Model
{
    protected $table = 'admin_roles';

    /** Rang des rôles personnalisés : en dessous de l'administrateur. */
    public const RANG_MIN = 1;

    public const RANG_MAX_PERSONNALISE = 99;

    protected $fillable = [
        'slug',
        'nom',
        'description',
        'rang',
        'systeme',
        'actif',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'rang' => 'integer',
            'systeme' => 'boolean',
            'actif' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(AdminUser::class, 'role', 'slug');
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function isAdministrateur(): bool
    {
        return $this->slug === AdminRole::Administrateur->value;
    }

    /**
     * Permissions du rôle, limitées au catalogue et à ce que son rang autorise.
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        if ($this->isAdministrateur()) {
            return AdminPermissions::allKeys();
        }

        return array_values(array_filter(
            AdminPermissions::sanitize($this->permissions ?? []),
            fn (string $key) => AdminPermissions::allowedForRank($key, $this->rang),
        ));
    }
}
