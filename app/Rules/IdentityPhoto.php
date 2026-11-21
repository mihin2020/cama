<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Photo d'identité d'un membre : la seule pièce qui reste une image (JPEG, PNG ou WebP).
 * Le type réel du contenu est vérifié (finfo, puis lecture de l'image), jamais le nom du fichier.
 */
class IdentityPhoto implements ValidationRule
{
    public const MAX_KILOBYTES = 5120;

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('La photo n\'a pas pu être envoyée : réessayez.');

            return;
        }

        if ($value->getSize() > self::MAX_KILOBYTES * 1024) {
            $fail('Photo trop volumineuse (5 Mo maximum).');

            return;
        }

        $info = @getimagesize($value->getRealPath());

        if (! in_array($value->getMimeType(), self::MIME_TYPES, true)
            || $info === false
            || ! in_array($info['mime'] ?? '', self::MIME_TYPES, true)) {
            $fail('Format non accepté : la photo doit être une image JPEG, PNG ou WebP.');
        }
    }
}
