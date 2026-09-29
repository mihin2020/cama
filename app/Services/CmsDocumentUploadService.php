<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CmsDocumentUploadService
{
    public function store(UploadedFile $file, string $subdir = 'cms'): string
    {
        $directory = public_path("documents/{$subdir}");

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'pdf');
        $filename = Str::uuid().'.'.$extension;

        $file->move($directory, $filename);

        return "documents/{$subdir}/{$filename}";
    }

    public function deleteIfUploaded(?string $relativePath): void
    {
        if (! $relativePath || ! str_starts_with($relativePath, 'documents/cms/')) {
            return;
        }

        $fullPath = public_path($relativePath);

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
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

        return '/'.ltrim($path, '/');
    }
}
