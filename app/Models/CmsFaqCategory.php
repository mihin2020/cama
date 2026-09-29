<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsFaqCategory extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function faqs(): HasMany
    {
        return $this->hasMany(CmsFaq::class, 'category_id');
    }
}
