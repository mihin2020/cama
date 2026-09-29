<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable;

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
        'invited_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'actif' => 'boolean',
            'role' => AdminRole::class,
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

    public function getDisplayNameAttribute(): string
    {
        $prefix = match ($this->role) {
            AdminRole::Gestionnaire => 'Lt. ',
            AdminRole::Superviseur => 'Cdt. ',
            AdminRole::Administrateur => 'Ing. ',
            AdminRole::Direction => 'Col-Maj. ',
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
        return AdminPermissions::effective($this->permissions, $this->role);
    }

    public function hasPermission(string $permission): bool
    {
        return app(\App\Services\AdminPermissionService::class)->allows($this, $permission);
    }
}
