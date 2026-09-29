<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsNewsletterTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'blocks_json',
        'html_preview',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'blocks_json' => 'array',
            'is_system' => 'boolean',
        ];
    }
}
