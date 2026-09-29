<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CmsImageUploadService
{
    public function store(UploadedFile $file, string $subdir = 'cms'): string
    {
        $directory = public_path("images/{$subdir}");

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $filename = Str::uuid().'.'.$extension;

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
