<?php

namespace App\Models;

use App\Models\Concerns\SanitizesSections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPageVersion extends Model
{
    use SanitizesSections;

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

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
