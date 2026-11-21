<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identifiant public : les adresses et les pages n'exposent jamais le numéro séquentiel (id),
 * qui se devine (/dossiers/10, /dossiers/11…), mais un UUID impossible à deviner.
 */
trait HasUuid
{
    /** @var array<string, bool> Colonne présente ? (les anciennes migrations tournent avant son ajout) */
    private static array $uuidColumn = [];

    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            $table = $model->getTable();

            // Seule la présence est mémorisée : la colonne apparaît en cours de migration.
            if (empty(self::$uuidColumn[$table]) && Schema::hasColumn($table, 'uuid')) {
                self::$uuidColumn[$table] = true;
            }

            if (! empty(self::$uuidColumn[$table]) && empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
