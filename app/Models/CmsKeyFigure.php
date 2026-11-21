<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\PublicSiteService;
use Illuminate\Database\Eloquent\Model;

class CmsKeyFigure extends Model
{
    use HasUuid;

    protected $fillable = [
        'value',
        'suffix',
        'label',
        'icon',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(PublicSiteService::class)->forgetCaches();
        static::saved($flush);
        static::deleted($flush);
    }
}
