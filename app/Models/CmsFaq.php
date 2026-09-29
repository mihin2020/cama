<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsFaq extends Model
{
    protected $table = 'cms_faq';

    protected $fillable = [
        'question',
        'answer',
        'category_id',
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
        return $this->belongsTo(CmsFaqCategory::class, 'category_id');
    }
}
