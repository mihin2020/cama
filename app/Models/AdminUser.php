<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Casts\AdminRoleSlug;
use App\Enums\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable, HasUuid;

    protected $fillable = [
        'nom',
        'prenom',
        'grade',
        'email',
        'password',
        'role',
        'permissions',
        'matricule_interne',
        'actif',
        'deux_fa_active',
        'deconnexion_auto_active',
        'invited_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'login_2fa_code',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'actif' => 'boolean',
            'deux_fa_active' => 'boolean',
            'deconnexion_auto_active' => 'boolean',
            'login_2fa_expires_at' => 'datetime',
            'login_2fa_attempts' => 'integer',
            'role' => AdminRoleSlug::class,
            'permissions' => 'array',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    public function getInitialesAttribute(): string
    {
        return strtoupper(substr($this->prenom, 0, 1).substr($this->nom, 0, 1));
    }

    /** Rôle du compte (table admin_roles), via le slug stocké dans « role ». */
    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    /** Rôle chargé, rechargé si le slug a changé depuis le dernier accès. */
    public function roleRecord(): ?Role
    {
        $loaded = $this->relationLoaded('roleDefinition') ? $this->getRelation('roleDefinition') : null;

        if (! $loaded || $loaded->slug !== $this->role) {
            $this->unsetRelation('roleDefinition');
            $loaded = $this->roleDefinition;
        }

        return $loaded;
    }

    public function hasRole(AdminRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === ($role instanceof AdminRole ? $role->value : $role)) {
                return true;
            }
        }

        return false;
    }

    public function isAdministrateur(): bool
    {
        return $this->hasRole(AdminRole::Administrateur);
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->roleRecord()?->nom ?? (string) $this->role;
    }

    /** Rang hiérarchique du rôle (0 si le rôle est introuvable : aucun droit de gestion). */
    public function getRoleRangAttribute(): int
    {
        return $this->roleRecord()?->rang ?? 0;
    }

    /** Le rôle du compte existe-t-il et est-il actif ? */
    public function roleIsActive(): bool
    {
        return (bool) $this->roleRecord()?->actif;
    }

    public function getDisplayNameAttribute(): string
    {
        $prefix = match (AdminRole::tryFrom((string) $this->role)) {
            AdminRole::Gestionnaire => 'Lt. ',
            AdminRole::Superviseur => 'Cdt. ',
            AdminRole::Administrateur => 'Ing. ',
            AdminRole::Direction => 'Col-Maj. ',
            default => '',
        };

        return $prefix.$this->full_name;
    }

    public function getDisplayNameWithGradeAttribute(): string
    {
        return trim(collect([$this->grade, $this->prenom, $this->nom])->filter()->implode(' '));
    }

    public function getStatutLabelAttribute(): string
    {
        if (! $this->actif) {
            return 'Désactivé';
        }

        if ($this->invited_at && ! $this->last_login_at) {
            return 'Invité';
        }

        return 'Actif';
    }

    /**
     * @return list<string>
     */
    public function getEffectivePermissionsAttribute(): array
    {
        return AdminPermissions::effective($this->permissions, $this->roleRecord());
    }

    public function hasPermission(string $permission): bool
    {
        return app(\App\Services\AdminPermissionService::class)->allows($this, $permission);
    }

    /** Sans « Consulter tous les dossiers », l'agent ne voit que les dossiers qui lui sont affectés. */
    public function seesOnlyOwnDossiers(): bool
    {
        return ! $this->hasPermission('dossiers.view_all');
    }
}
