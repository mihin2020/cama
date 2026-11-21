<?php

namespace App\Services;

use App\Support\SafeUpload;
use App\Support\SiteUploads;
use App\Support\UploadFilename;
use Illuminate\Http\UploadedFile;

class CmsDocumentUploadService
{
    /**
     * @param  string  $owner  à quoi le document est lié (ex. « RESSOURCE-GUIDE-ASSURE »)
     */
    public function store(UploadedFile $file, string $subdir = 'cms', string $owner = 'RESSOURCE'): string
    {
        $folder = "documents/{$subdir}";
        $extension = SafeUpload::extension($file, [...SafeUpload::DOCUMENTS, 'png', 'jpg'], 'document_file');
        $filename = UploadFilename::makeUnique(
            fn (string $name) => SiteUploads::nameTaken($folder, $name),
            UploadFilename::CMS,
            $owner,
            'DOCUMENT',
            $extension,
        );

        // Rangé sur le stockage de Laravel (voir App\Support\SiteUploads), pas dans public/.
        return SiteUploads::store($file, $folder, $filename);
    }

    public function deleteIfUploaded(?string $relativePath): void
    {
        SiteUploads::delete($relativePath);
    }

    public static function formatFromFilename(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'PDF',
            'doc', 'docx' => 'DOC',
            'xls', 'xlsx' => 'XLS',
            'png', 'jpg', 'jpeg', 'webp' => 'IMG',
            default => strtoupper($extension ?: 'PDF'),
        };
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return (int) round($bytes / 1024).' Ko';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', '').' Mo';
    }

    public static function resolveFileUrl(?string $path): ?string
    {
        if (! $path || $path === '#') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return SiteUploads::url($path);
    }
}
