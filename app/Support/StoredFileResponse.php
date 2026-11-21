<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Réponse commune pour tout fichier envoyé par un assuré (pièces d'inscription, pièces des membres,
 * FIF signée) : « Visualiser » l'ouvre dans le navigateur, « Télécharger » l'enregistre.
 *
 * Seuls les PDF et les images peuvent s'afficher dans le navigateur ; tout le reste est téléchargé.
 * Le type est lu dans le contenu du fichier, jamais dans son nom, et « nosniff » empêche le
 * navigateur de le réinterpréter.
 */
final class StoredFileResponse
{
    /** Formats affichables dans le navigateur. */
    private const INLINE_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public static function make(string $absolutePath, ?string $filename, bool $inline): BinaryFileResponse
    {
        $filename = $filename ?: basename($absolutePath);
        $mime = File::mimeType($absolutePath) ?: 'application/octet-stream';
        $viewable = $inline && in_array($mime, self::INLINE_TYPES, true);

        // Nom encodé proprement (RFC 6266), avec une version ASCII de secours.
        $disposition = HeaderUtils::makeDisposition(
            $viewable ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            Str::ascii($filename) ?: 'fichier',
        );

        return response()->file($absolutePath, [
            'Content-Type' => $viewable ? $mime : 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
