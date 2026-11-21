<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fichiers du site public envoyés par l'administration (images d'articles, bannières, partenaires,
 * documents de ressources, médiathèque, logo).
 *
 * Ils sont rangés sur le stockage de Laravel, pas dans « public/ » : le dossier « public/ » fait
 * partie du code et peut être remplacé à chaque déploiement, alors que « storage/ » (ou un stockage
 * distant) est conservé. Le disque se règle par SITE_UPLOADS_DISK (« public » par défaut ; « s3 »
 * pour un stockage distant).
 *
 * Avec le disque « public », les fichiers sont servis à l'adresse /storage/site/… : directement par
 * le serveur web si le lien « public/storage » existe (php artisan storage:link), sinon par Laravel.
 *
 * Références enregistrées en base (« ref ») : « storage/site/images/cms/NOM » (disque local) ou une
 * adresse complète (stockage distant). Les anciennes références (« images/cms/NOM », fichiers
 * rangés dans public/) restent comprises, en lecture comme en suppression, jusqu'à leur reprise par
 * la commande « php artisan uploads:move-to-storage ».
 */
final class SiteUploads
{
    /** Dossier racine sur le disque. */
    public const ROOT = 'site';

    /** Anciens dossiers de public/, avant le passage au stockage de Laravel. */
    public const LEGACY_FOLDERS = ['images/cms', 'images/partners', 'documents/cms', 'media/cms', 'media/branding'];

    /**
     * Dossier qui contient les anciens fichiers (public/ par défaut). Réglable (filesystems.legacy_uploads_root)
     * pour que les tests n'approchent jamais des vrais fichiers du site.
     */
    public static function legacyRoot(): string
    {
        return rtrim((string) (config('filesystems.legacy_uploads_root') ?: public_path()), '/'.DIRECTORY_SEPARATOR);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function diskName(): string
    {
        return (string) config('filesystems.site_uploads_disk', 'public');
    }

    /** Range le fichier et retourne sa référence (à enregistrer en base). */
    public static function store(UploadedFile $file, string $folder, string $name): string
    {
        $path = self::ROOT.'/'.trim($folder, '/').'/'.$name;

        self::disk()->putFileAs(self::ROOT.'/'.trim($folder, '/'), $file, $name, 'public');

        return self::ref($path);
    }

    /** Un nom de fichier est-il déjà pris dans ce dossier ? (pour garantir des noms uniques) */
    public static function nameTaken(string $folder, string $name): bool
    {
        return self::disk()->exists(self::ROOT.'/'.trim($folder, '/').'/'.$name);
    }

    /** Référence d'un fichier du disque : relative pour le disque local, adresse complète sinon. */
    public static function ref(string $diskPath): string
    {
        return self::isLocalPublicDisk() ? 'storage/'.ltrim($diskPath, '/') : self::disk()->url($diskPath);
    }

    /** Adresse à mettre dans une page (avec « / » initial pour une référence relative). */
    public static function url(?string $ref): ?string
    {
        if ($ref === null || $ref === '' || $ref === '#') {
            return null;
        }

        return preg_match('~^https?://~i', $ref) ? $ref : '/'.ltrim($ref, '/');
    }

    /** Chemin sur le disque d'une référence gérée ici (null pour une ancienne référence ou un fichier externe). */
    public static function diskPath(?string $ref): ?string
    {
        $ref = (string) $ref;

        if (preg_match('~^/?storage/('.preg_quote(self::ROOT, '~').'/.+)$~', $ref, $m)) {
            return $m[1];
        }

        if (! self::isLocalPublicDisk() && preg_match('~^https?://~i', $ref)) {
            $base = rtrim(self::disk()->url(''), '/').'/';

            return str_starts_with($ref, $base) ? substr($ref, strlen($base)) : null;
        }

        return null;
    }

    /** Ancien emplacement dans public/ (« images/cms/NOM »), ou null. */
    public static function legacyPath(?string $ref): ?string
    {
        $path = ltrim((string) $ref, '/');

        foreach (self::LEGACY_FOLDERS as $folder) {
            if (str_starts_with($path, $folder.'/') && ! str_contains($path, '..')) {
                return $path;
            }
        }

        return null;
    }

    public static function exists(?string $ref): bool
    {
        if ($diskPath = self::diskPath($ref)) {
            return self::disk()->exists($diskPath);
        }

        $legacy = self::legacyPath($ref);

        return $legacy !== null && is_file(self::legacyRoot().DIRECTORY_SEPARATOR.$legacy);
    }

    /** Supprime un fichier envoyé par l'administration (jamais un fichier d'origine du site). */
    public static function delete(?string $ref): void
    {
        if ($diskPath = self::diskPath($ref)) {
            self::disk()->delete($diskPath);

            return;
        }

        if (($legacy = self::legacyPath($ref)) && is_file($file = self::legacyRoot().DIRECTORY_SEPARATOR.$legacy)) {
            @unlink($file);
        }
    }

    public static function isLocalPublicDisk(): bool
    {
        return config('filesystems.disks.'.self::diskName().'.driver') === 'local';
    }
}
