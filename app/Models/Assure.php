<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Enums\AssureStatut;
use App\Services\FifProfileService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Assure extends Authenticatable
{
    use HasFactory, Notifiable, HasUuid;

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
        'fif_donnees',
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
        'email_verification_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'login_2fa_expires_at' => 'datetime',
            'login_2fa_attempts' => 'integer',
            'email_verification_expires_at' => 'datetime',
            'email_verification_attempts' => 'integer',
            'password' => 'hashed',
            'deux_fa_active' => 'boolean',
            'statut' => AssureStatut::class,
            'journal' => 'array',
            'documents_identite' => 'array',
            'fif_donnees' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Numéro de Fiche d'Identification Famille (ex. 26AT1R001) attribué une fois pour toutes à l'inscription.
        static::created(function (Assure $assure) {
            if (! $assure->numero_fif) {
                app(FifProfileService::class)->assign($assure);
            }
        });
    }

    /** Adresse toujours enregistrée en minuscules (comparaisons identiques sous MySQL et PostgreSQL). */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
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
