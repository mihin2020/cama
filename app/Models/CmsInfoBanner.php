<?php

namespace App\Models;

use App\Services\PublicSiteService;
use Illuminate\Database\Eloquent\Model;

class CmsInfoBanner extends Model
{
    protected $table = 'cms_info_banner';

    protected $fillable = [
        'active',
        'type',
        'dismissible',
        'scrolling',
        'scroll_speed',
        'bg_color',
        'text_color',
        'message',
        'link_url',
        'link_label',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'dismissible' => 'boolean',
            'scrolling' => 'boolean',
            'scroll_speed' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(PublicSiteService::class)->forgetCaches();
        static::saved($flush);
        static::deleted($flush);
    }
}
