<?php

namespace App\Models;

use App\Services\PublicSiteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsArticle extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'category_id',
        'status',
        'author_name',
        'published_at',
        'image_url',
        'excerpt',
        'body_html',
        'featured',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsArticleCategory::class, 'category_id');
    }

    protected static function booted(): void
    {
        $flush = fn () => app(PublicSiteService::class)->forgetCaches();
        static::saved($flush);
        static::deleted($flush);
    }
}
