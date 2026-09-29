<?php

namespace App\Models;

use App\Enums\AssureStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Assure extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'sexe',
        'matricule',
        'numero_informatique',
        'grade',
        'categorie',
        'numero_cim',
        'numero_cama',
        'numero_iup',
        'armee',
        'region',
        'corps',
        'service',
        'section',
        'sous_section',
        'telephone',
        'email',
        'personne_a_prevenir',
        'tel_personne_a_prevenir',
        'documents_identite',
        'password',
        'statut',
        'deux_fa_active',
        'motif_refus',
        'journal',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'login_2fa_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'login_2fa_expires_at' => 'datetime',
            'login_2fa_used_codes' => 'array',
            'password' => 'hashed',
            'deux_fa_active' => 'boolean',
            'statut' => AssureStatut::class,
            'journal' => 'array',
            'documents_identite' => 'array',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    public function peutSeConnecter(): bool
    {
        return ! in_array($this->statut, [AssureStatut::Refuse, AssureStatut::Desactive], true);
    }

    public function peutEnroler(): bool
    {
        return $this->statut === AssureStatut::Actif;
    }

    public function getInitialesAttribute(): string
    {
        return strtoupper(substr($this->prenom, 0, 1).substr($this->nom, 0, 1));
    }

    public function dossiers()
    {
        return $this->hasMany(Dossier::class);
    }

    public function notifications()
    {
        return $this->hasMany(AssureNotification::class);
    }
}
