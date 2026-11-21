<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Pièce justificative : un vrai fichier PDF, et rien d'autre.
 *
 * Le contrôle ne se fie ni au nom ni au type annoncé par le navigateur :
 *  - extension « .pdf » ;
 *  - type réel du contenu (finfo) = application/pdf ;
 *  - signature « %PDF- » au tout début du fichier ;
 *  - taille maximale (5 Mo par défaut) ;
 *  - aucun contenu actif (JavaScript, lancement de programme, fichier embarqué).
 *
 * Limite connue : la recherche du contenu actif lit les noms d'actions en clair. Un PDF qui les
 * cache dans un flux compressé n'est pas détecté ; la parade de fond est de ne jamais exécuter
 * ces fichiers (ils sont servis en téléchargement, avec « nosniff », depuis un espace privé).
 */
class PdfDocument implements ValidationRule
{
    public const MAX_KILOBYTES = 5120;

    /** Actions PDF qui exécutent du code ou lancent un programme. */
    private const ACTIVE_CONTENT = '~/(?:JavaScript|JS|Launch|EmbeddedFile)(?![A-Za-z0-9])~';

    public function __construct(private int $maxKilobytes = self::MAX_KILOBYTES) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Le fichier n\'a pas pu être envoyé : réessayez.');

            return;
        }

        if ($value->getSize() > $this->maxKilobytes * 1024) {
            $fail('Fichier trop volumineux ('.$this->maxMegabytes().' Mo maximum).');

            return;
        }

        if (strtolower($value->getClientOriginalExtension()) !== 'pdf'
            || $value->getMimeType() !== 'application/pdf'
            || ! $this->startsWithPdfSignature($value)) {
            $fail('Format non accepté : seuls les fichiers PDF sont autorisés.');

            return;
        }

        if (preg_match(self::ACTIVE_CONTENT, (string) file_get_contents($value->getRealPath()))) {
            $fail('Ce PDF contient du contenu actif (script, programme ou fichier joint) : il est refusé. Enregistrez-le de nouveau en « PDF standard » ou rescannez le document.');
        }
    }

    private function startsWithPdfSignature(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 5);
        fclose($handle);

        return $head === '%PDF-';
    }

    private function maxMegabytes(): string
    {
        return rtrim(rtrim(number_format($this->maxKilobytes / 1024, 1, ',', ''), '0'), ',');
    }
}
