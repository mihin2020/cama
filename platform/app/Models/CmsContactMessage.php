<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsContactMessage extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'subject',
        'message',
        'consent',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
        'archived_at',
        'source_page',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'handled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'handled_by');
    }
}
