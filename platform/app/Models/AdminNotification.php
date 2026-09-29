<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotification extends Model
{
    protected $fillable = [
        'admin_user_id',
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

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
