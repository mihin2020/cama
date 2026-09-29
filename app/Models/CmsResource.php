<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsResource extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category_id',
        'format',
        'file_size',
        'file_url',
        'sort_order',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsResourceCategory::class, 'category_id');
    }
}
