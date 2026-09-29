<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dossier extends Model
{
    protected $fillable = [
        'assure_id',
        'ref',
        'nom',
        'prenom',
        'lien',
        'sexe',
        'date_naissance',
        'membre_numero_cama',
        'statut',
        'gestionnaire',
        'date_soumission',
        'date_decision',
        'motif_refus',
        'journal',
        'pieces',
        'wizard_meta',
        'lot_id',
        'lot_type',
    ];

    protected function casts(): array
    {
        return [
            'date_soumission' => 'date',
            'date_decision' => 'date',
            'date_naissance' => 'date',
            'journal' => 'array',
            'pieces' => 'array',
            'wizard_meta' => 'array',
        ];
    }

    public function assure(): BelongsTo
    {
        return $this->belongsTo(Assure::class);
    }

    public function getBeneficiaireAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
