<?php

namespace App\Models;

use App\Services\PublicSiteService;
use Illuminate\Database\Eloquent\Model;

class CmsSlide extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'link_url',
        'link_label',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(PublicSiteService::class)->forgetCaches();
        static::saved($flush);
        static::deleted($flush);
    }
}
