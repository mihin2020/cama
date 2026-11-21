<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Section du formulaire FIF d'une cible (assuré, conjoint ou enfant), définie par l'administration. */
class FifSection extends Model
{
    use SoftDeletes, HasUuid;

    public const CIBLES = ['assure' => 'Assuré (militaire)', 'conjoint' => 'Conjoint(e)', 'enfant' => 'Enfant'];

    protected $fillable = ['cible', 'code', 'titre', 'description', 'ordre', 'visible'];

    protected function casts(): array
    {
        return ['visible' => 'boolean', 'ordre' => 'integer'];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FifField::class, 'section_id')->orderBy('ordre')->orderBy('id');
    }
}
