<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPartner extends Model
{
    protected $fillable = [
        'name',
        'type',
        'city',
        'address',
        'phone',
        'email',
        'hours',
        'description',
        'image_url',
        'latitude',
        'longitude',
        'maps_url',
        'sort_order',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'published' => 'boolean',
        ];
    }
}
