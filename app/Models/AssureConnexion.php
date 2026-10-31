<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssureConnexion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['assure_id', 'ip', 'appareil', 'inhabituelle'];

    protected function casts(): array
    {
        return ['inhabituelle' => 'boolean'];
    }
}
