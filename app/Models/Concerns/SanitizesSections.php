<?php

namespace App\Models\Concerns;

use App\Support\CmsSectionsSanitizer;

/**
 * Toute écriture de sections_json (éditeur, version restaurée, duplication) passe par le filtre
 * du page builder : pages comme versions, qui sont prévisualisées et restaurables.
 */
trait SanitizesSections
{
    protected static function bootSanitizesSections(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('sections_json') && is_array($model->sections_json)) {
                $model->sections_json = CmsSectionsSanitizer::sanitize($model->sections_json);
            }
        });
    }
}
