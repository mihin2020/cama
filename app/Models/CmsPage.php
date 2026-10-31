<?php

namespace App\Models;

use App\Services\PublicSiteService;
use App\Support\CmsSectionsSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPage extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'status',
        'sections_json',
        'author_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'sections_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'author_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsPageVersion::class);
    }

    protected static function booted(): void
    {
        // Toute écriture des sections (éditeur, version restaurée, duplication) passe par le filtre.
        static::saving(function (CmsPage $page) {
            if ($page->isDirty('sections_json') && is_array($page->sections_json)) {
                $page->sections_json = CmsSectionsSanitizer::sanitize($page->sections_json);
            }
        });
        static::saved(fn (CmsPage $page) => app(PublicSiteService::class)->forgetCaches($page->slug));
        static::deleted(fn (CmsPage $page) => app(PublicSiteService::class)->forgetCaches($page->slug));
    }
}
