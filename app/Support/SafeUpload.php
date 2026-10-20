<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Extension de fichier sûre pour les envois rangés dans public/ (médiathèque, images, documents).
 *
 * L'extension enregistrée est déduite du contenu réel du fichier, jamais du nom choisi par
 * l'expéditeur, et doit figurer dans une liste autorisée : un fichier ne peut donc pas être
 * servi autrement que comme une image ou un document.
 */
class SafeUpload
{
    public const IMAGES = ['jpg', 'png', 'webp', 'gif'];

    public const DOCUMENTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

    /** Formats Office récents : ce sont des archives ZIP, parfois détectées comme telles. */
    private const OFFICE_ZIP = ['docx', 'xlsx'];

    /**
     * @param  list<string>  $allowed
     *
     * @throws ValidationException
     */
    public static function extension(UploadedFile $file, array $allowed, string $field = 'file'): string
    {
        $extension = match ($detected = strtolower((string) $file->guessExtension())) {
            'jpeg', 'jpe', 'jfif' => 'jpg',
            default => $detected,
        };

        $client = strtolower($file->getClientOriginalExtension());
        if ($extension === 'zip' && in_array($client, self::OFFICE_ZIP, true) && in_array($client, $allowed, true)) {
            $extension = $client;
        }

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                $field => 'Type de fichier non autorisé. Formats acceptés : '.strtoupper(implode(', ', $allowed)).'.',
            ]);
        }

        return $extension;
    }
}
