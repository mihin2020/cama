<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMenuItem extends Model
{
    protected $fillable = [
        'type',
        'page_id',
        'label',
        'url',
        'depth',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }
}
