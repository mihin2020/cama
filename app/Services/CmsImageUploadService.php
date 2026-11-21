<?php

namespace App\Services;

use App\Support\SafeUpload;
use App\Support\SiteUploads;
use App\Support\UploadFilename;
use Illuminate\Http\UploadedFile;

class CmsImageUploadService
{
    /**
     * @param  string  $owner  à quoi l'image est liée (ex. « ARTICLE-RENTREE-2026 ») : sert au nom du fichier
     */
    public function store(UploadedFile $file, string $subdir = 'cms', string $owner = 'SITE'): string
    {
        $folder = "images/{$subdir}";
        $extension = SafeUpload::extension($file, SafeUpload::IMAGES, 'image_file');
        $filename = UploadFilename::makeUnique(
            fn (string $name) => SiteUploads::nameTaken($folder, $name),
            UploadFilename::CMS,
            $owner,
            'IMAGE',
            $extension,
        );

        // Rangé sur le stockage de Laravel (voir App\Support\SiteUploads), pas dans public/.
        return SiteUploads::store($file, $folder, $filename);
    }

    /** Supprime l'image si elle a été envoyée par l'administration (jamais une image d'origine du site). */
    public function deleteIfUploaded(?string $relativePath): void
    {
        SiteUploads::delete($relativePath);
    }
}
