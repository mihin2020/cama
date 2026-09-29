<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMedia extends Model
{
    protected $table = 'cms_media';

    protected $fillable = [
        'disk_path',
        'url',
        'name',
        'original_name',
        'title',
        'alt_text',
        'folder',
        'category',
        'tags',
        'description',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'uploaded_by');
    }
}
