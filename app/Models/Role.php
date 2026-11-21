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
 * comptes existants.
 *
 * La hiérarchie (qui gère qui) est interne et n'apparaît pas à l'écran : les rôles créés par
 * l'administrateur sont au niveau d'un gestionnaire.
 */
class Role extends Model
{
    protected $table = 'admin_roles';

    /** Niveau interne d'un rôle créé par l'administrateur : celui d'un gestionnaire. */
    public const RANG_PERSONNALISE = 10;

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
     * Permissions du rôle, limitées au catalogue et à ce que son niveau autorise.
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
