<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsResourceCategory extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function resources(): HasMany
    {
        return $this->hasMany(CmsResource::class, 'category_id');
    }
}
