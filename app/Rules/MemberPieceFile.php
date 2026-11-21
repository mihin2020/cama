<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fichier d'une pièce de membre : la photo du membre est une image, toutes les autres pièces sont
 * des PDF. Le type de pièce se lit à la fin du nom du champ (…piece_files.photo_membre).
 */
class MemberPieceFile implements ValidationRule
{
    public const PHOTO_KEY = 'photo_membre';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rule = str_ends_with($attribute, '.'.self::PHOTO_KEY) ? new IdentityPhoto : new PdfDocument;

        $rule->validate($attribute, $value, $fail);
    }
}
