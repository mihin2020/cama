<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncCamaPublicImages extends Command
{
    protected $signature = 'cama:sync-images';

    protected $description = 'Copie les images de la maquette (../images) vers public/images';

    public function handle(): int
    {
        $source = dirname(base_path()).DIRECTORY_SEPARATOR.'images';
        $dest = public_path('images');

        if (! is_dir($source)) {
            $this->error("Dossier source introuvable : {$source}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($dest);

        $count = 0;
        foreach (File::files($source) as $file) {
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp', 'svg'], true)) {
                continue;
            }

            File::copy($file->getPathname(), $dest.DIRECTORY_SEPARATOR.$file->getFilename());
            $count++;
        }

        $this->info("{$count} image(s) synchronisée(s) vers public/images.");

        return self::SUCCESS;
    }
}
