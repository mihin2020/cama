<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Champ d'une section du formulaire FIF. */
class FifField extends Model
{
    use SoftDeletes;

    /** Types proposés : valeur => libellé. */
    public const TYPES = [
        'text' => 'Texte court',
        'textarea' => 'Texte long',
        'number' => 'Nombre',
        'date' => 'Date',
        'select' => 'Liste déroulante',
        'radio' => 'Choix unique (boutons)',
        'checkbox' => 'Case à cocher (oui / non)',
        'email' => 'Adresse e-mail',
        'tel' => 'Téléphone',
        'fichier' => 'Fichier à charger (PDF)',
    ];

    /** Types qui proposent une liste de choix. */
    public const CHOICE_TYPES = ['select', 'radio'];

    protected $fillable = ['section_id', 'code', 'libelle', 'type', 'options', 'aide', 'obligatoire', 'visible', 'imprimer_pdf', 'ordre'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'obligatoire' => 'boolean',
            'visible' => 'boolean',
            'imprimer_pdf' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FifSection::class, 'section_id');
    }
}
