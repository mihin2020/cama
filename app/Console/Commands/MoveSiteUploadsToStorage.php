<?php

namespace App\Console\Commands;

use App\Support\SiteUploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reprend les fichiers du site public envoyés avant le passage au stockage de Laravel : ils étaient
 * rangés dans public/ (images/cms, images/partners, documents/cms, media/cms, media/branding), un
 * dossier que le déploiement peut remplacer.
 *
 * La commande copie chaque fichier sur le disque SITE_UPLOADS_DISK puis corrige les références en
 * base (articles, bannières, partenaires, ressources, médiathèque, pages, logo). Elle peut être
 * relancée sans danger. Les fichiers d'origine ne sont supprimés qu'avec --delete-originals, après
 * vérification du site.
 */
class MoveSiteUploadsToStorage extends Command
{
    protected $signature = 'uploads:move-to-storage {--delete-originals : Supprime les fichiers de public/ une fois copiés et références corrigées}';

    protected $description = 'Reprend les fichiers du site public rangés dans public/ vers le stockage de Laravel (conservé entre deux déploiements)';

    /** Colonnes de texte qui peuvent contenir l'adresse d'un fichier : table => [colonnes]. */
    private const TARGETS = [
        'cms_articles' => ['image_url', 'body_html'],
        'cms_slides' => ['image_url'],
        'cms_partners' => ['image_url'],
        'cms_resources' => ['file_url'],
        'cms_media' => ['disk_path', 'url'],
        'cms_pages' => ['sections_json'],
        'cms_page_versions' => ['sections_json'],
        'platform_settings' => ['value'],
    ];

    public function handle(): int
    {
        $disk = SiteUploads::disk();
        $moved = 0;
        $rewritten = 0;

        foreach (SiteUploads::LEGACY_FOLDERS as $folder) {
            $directory = SiteUploads::legacyRoot().DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($directory)) {
                continue;
            }

            foreach (new \DirectoryIterator($directory) as $entry) {
                if ($entry->isDot() || ! $entry->isFile() || str_starts_with($entry->getFilename(), '.')) {
                    continue;
                }

                $name = $entry->getFilename();
                $diskPath = SiteUploads::ROOT."/{$folder}/{$name}";

                if (! $disk->exists($diskPath)) {
                    $stream = fopen($entry->getPathname(), 'rb');
                    $disk->put($diskPath, $stream, 'public');
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $newRef = SiteUploads::ref($diskPath);
                $rewritten += $this->rewriteReferences("{$folder}/{$name}", $newRef);
                $moved++;

                if ($this->option('delete-originals')) {
                    @unlink($entry->getPathname());
                }
                $this->line("  {$folder}/{$name}  →  {$newRef}");
            }
        }

        $this->info("{$moved} fichier(s) repris sur le disque « ".SiteUploads::diskName()." », {$rewritten} référence(s) corrigée(s).");

        if ($moved > 0 && ! $this->option('delete-originals')) {
            $this->comment('Les originaux de public/ sont conservés. Après vérification du site : php artisan uploads:move-to-storage --delete-originals');
        }

        if (SiteUploads::isLocalPublicDisk() && ! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            $this->comment('Lien « public/storage » absent : les fichiers sont servis par Laravel. Pour les servir directement par le serveur web : php artisan storage:link');
        }

        return self::SUCCESS;
    }

    /** Remplace l'ancienne adresse du fichier par la nouvelle dans toutes les colonnes concernées. */
    private function rewriteReferences(string $oldPath, string $newRef): int
    {
        // Le JSON enregistré par Laravel échappe les « / » (images\/cms\/…) : on traite les deux écritures.
        $variants = [[$oldPath, $newRef], [str_replace('/', '\\/', $oldPath), str_replace('/', '\\/', $newRef)]];
        $count = 0;

        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $key = $table === 'platform_settings' ? 'key' : 'id';

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                // Filtrage en PHP (et non par LIKE) : compatible avec les colonnes JSON de PostgreSQL.
                $needle = basename($oldPath);

                foreach (DB::table($table)->select($key, $column)->orderBy($key)->cursor() as $row) {
                    $value = (string) $row->{$column};

                    if ($value === '' || ! str_contains($value, $needle)) {
                        continue;
                    }

                    // Déjà corrigé (relance de la commande) : rien à faire.
                    if (str_contains($value, $newRef) || str_contains($value, str_replace('/', '\/', $newRef))) {
                        continue;
                    }

                    $updated = $value;
                    foreach ($variants as [$old, $new]) {
                        $updated = str_replace($old, $new, $updated);
                    }

                    if ($updated !== $value) {
                        DB::table($table)->where($key, $row->{$key})->update([$column => $updated]);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
