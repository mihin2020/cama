<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsActivityLog extends Model
{
    protected $fillable = [
        'admin_user_id',
        'icon',
        'text',
        'author_name',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
