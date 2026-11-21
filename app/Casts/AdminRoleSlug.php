<?php

namespace App\Casts;

use App\Enums\AdminRole;
use App\Models\Role;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * admin_users.role : slug d'un rôle de la table admin_roles. Accepte en écriture un slug, un rôle
 * système (enum AdminRole) ou un modèle Role. Cast « entrant » uniquement : la lecture renvoie
 * toujours le slug stocké (string), jamais l'objet passé en écriture.
 */
class AdminRoleSlug implements CastsInboundAttributes
{
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value instanceof AdminRole => $value->value,
            $value instanceof Role => $value->slug,
            $value === null => null,
            default => (string) $value,
        };
    }
}
