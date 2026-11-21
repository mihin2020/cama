<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsFaqCategory extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function faqs(): HasMany
    {
        return $this->hasMany(CmsFaq::class, 'category_id');
    }
}
