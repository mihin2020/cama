<?php

namespace App\Services;

use App\Support\SafeUpload;
use App\Support\UploadFilename;
use Illuminate\Http\UploadedFile;

class CmsImageUploadService
{
    /**
     * @param  string  $owner  à quoi l'image est liée (ex. « ARTICLE-RENTREE-2026 ») : sert au nom du fichier
     */
    public function store(UploadedFile $file, string $subdir = 'cms', string $owner = 'SITE'): string
    {
        $directory = public_path("images/{$subdir}");

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = SafeUpload::extension($file, SafeUpload::IMAGES, 'image_file');
        $filename = UploadFilename::makeUnique(
            fn (string $name) => is_file($directory.DIRECTORY_SEPARATOR.$name),
            UploadFilename::CMS,
            $owner,
            'IMAGE',
            $extension,
        );

        $file->move($directory, $filename);

        return "images/{$subdir}/{$filename}";
    }

    public function deleteIfUploaded(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $managed = ['images/cms/', 'images/partners/'];
        $isManaged = false;
        foreach ($managed as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                $isManaged = true;
                break;
            }
        }
        if (! $isManaged) {
            return;
        }

        $fullPath = public_path($relativePath);

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
