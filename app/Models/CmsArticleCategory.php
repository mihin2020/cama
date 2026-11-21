<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsArticleCategory extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(CmsArticle::class, 'category_id');
    }
}
