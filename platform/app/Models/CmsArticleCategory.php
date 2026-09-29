<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsArticleCategory extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(CmsArticle::class, 'category_id');
    }
}
