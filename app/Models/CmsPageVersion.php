<?php

namespace App\Models;

use App\Support\CmsSectionsSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPageVersion extends Model
{
    protected $fillable = [
        'cms_page_id',
        'version_number',
        'event',
        'comment',
        'title',
        'slug',
        'status',
        'sections_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sections_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Les versions sont affichées en aperçu et peuvent être restaurées : même filtre que les pages.
        static::saving(function (CmsPageVersion $version) {
            if ($version->isDirty('sections_json') && is_array($version->sections_json)) {
                $version->sections_json = CmsSectionsSanitizer::sanitize($version->sections_json);
            }
        });
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
