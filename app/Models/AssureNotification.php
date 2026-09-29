<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssureNotification extends Model
{
    protected $fillable = [
        'assure_id',
        'type',
        'titre',
        'contenu',
        'lien',
        'lu',
    ];

    protected function casts(): array
    {
        return [
            'lu' => 'boolean',
        ];
    }

    public function assure(): BelongsTo
    {
        return $this->belongsTo(Assure::class);
    }
}
