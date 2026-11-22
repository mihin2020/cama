<?php

namespace App\Services;

use App\Models\Assure;
use App\Models\Dossier;
use App\Rules\IdentityPhoto;
use App\Rules\MemberPieceFile;
use App\Rules\PdfDocument;
use App\Support\UploadFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Stockage privé des pièces des assurés (disque « local », jamais accessible directement par le web).
 *
 *  - toutes les pièces sont des PDF (contrôlés sur le contenu réel) ; seule la photo d'un membre
 *    reste une image ;
 *  - un dossier par assuré, nommé d'après son N° FIF : assures/26AT1R001/ ;
 *  - chaque fichier porte un nom normalisé (voir App\Support\UploadFilename), qui est aussi le nom
 *    proposé au téléchargement ; le nom d'origine est conservé dans « original_name ».
 */
class DossierPieceStorageService
{
    public const DISK = 'local';

    public function storeLotPiece(Assure $assure, UploadedFile $file, string $type): array
    {
        $stored = $this->store($assure, $file, $this->owner($assure), $type, 'fif_signee');

        return [
            'type' => $type,
            'statut' => 'Soumise',
            ...$stored,
            'lot' => true,
        ];
    }

    /**
     * Stocke une pièce justificative d'un membre (conjoint/enfant) et retourne ses métadonnées.
     *
     * @param  string|null  $memberCode  CONJ01, ENF02… (voir UploadFilename::memberCode)
     * @return array<string, mixed>
     */
    public function storeMemberPiece(Assure $assure, UploadedFile $file, string $key, string $type, ?string $memberCode = null): array
    {
        $owner = $this->owner($assure).($memberCode ? "-{$memberCode}" : '');
        $stored = $this->store($assure, $file, $owner, $type, "piece.{$key}", $key === MemberPieceFile::PHOTO_KEY);

        return [
            'key' => $key,
            'type' => $type,
            'statut' => 'Soumise',
            ...$stored,
        ];
    }

    /**
     * Pièce d'inscription de l'assuré (carte militaire, CNIB…). Le compte existe déjà : son N° FIF
     * sert au nom du fichier.
     *
     * @return array<string, mixed>
     */
    public function storeIdentityDocument(Assure $assure, UploadedFile $file, string $key, string $label): array
    {
        $stored = $this->store($assure, $file, $this->owner($assure), "INSCR {$label}", "documents.{$key}");

        return ['key' => $key, 'label' => $label, ...$stored];
    }

    /**
     * Fichier d'un champ « fichier » du formulaire FIF (PDF, comme toute pièce), rangé avec les autres
     * pièces de l'assuré sous un nom normalisé.
     *
     * @return array{filename: string, original_name: string, path: string, mime: string, size: int}
     */
    public function storeFifFile(Assure $assure, UploadedFile $file, string $label, ?string $memberCode = null, string $field = 'fif'): array
    {
        $owner = $this->owner($assure).($memberCode ? "-{$memberCode}" : '');

        return $this->store($assure, $file, $owner, $label, $field);
    }

    /** Supprime un fichier rangé (ex. remplacé par un nouveau). */
    public function deleteStored(?string $path): void
    {
        if ($path && (str_starts_with($path, 'assures/') || str_starts_with($path, 'inscriptions/'))) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** Code du membre d'un dossier existant : rang parmi les conjoints ou les enfants de l'assuré. */
    public function memberCodeFor(Assure $assure, Dossier $dossier): string
    {
        $conjoint = stripos((string) $dossier->lien, 'conjoint') !== false;

        $position = $assure->dossiers()
            ->when($conjoint, fn ($q) => $q->whereLike('lien', '%onjoint%'), fn ($q) => $q->whereNotLike('lien', '%onjoint%'))
            ->where('id', '<=', $dossier->id)
            ->count();

        return UploadFilename::memberCode($conjoint, $position);
    }

    public function diskPath(?string $path): ?string
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->path($path);
    }

    /**
     * @return array{filename: string, original_name: string, path: string, mime: string, size: int}
     */
    private function store(Assure $assure, UploadedFile $file, string $owner, string $type, string $field, bool $photo = false): array
    {
        // Le contrôle porte sur le contenu réel du fichier, pas sur son nom ni sur le type annoncé.
        // Clé neutre : un nom de champ à points (« piece.acte ») serait lu comme une imbrication et
        // le fichier ne serait jamais contrôlé. L'erreur est rattachée au champ de l'écran.
        $validator = Validator::make(['fichier' => $file], ['fichier' => [$photo ? new IdentityPhoto : new PdfDocument]]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([$field => $validator->errors()->first('fichier')]);
        }

        $disk = Storage::disk(self::DISK);
        $directory = 'assures/'.UploadFilename::segment($this->owner($assure));
        $extension = $photo ? $this->imageExtension($file) : 'pdf';
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $original = $file->getClientOriginalName();

        $name = UploadFilename::makeUnique(
            fn (string $candidate) => $disk->exists("{$directory}/{$candidate}"),
            UploadFilename::ASSURE,
            $owner,
            $type,
            $extension,
        );

        $path = $disk->putFileAs($directory, $file, $name);

        return [
            'filename' => $name,
            'original_name' => $original,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /** N° FIF de l'assuré (ou, à défaut, son identifiant) : sert de clé de rangement et de nommage. */
    private function owner(Assure $assure): string
    {
        return $assure->numero_fif ?: "ID{$assure->id}";
    }

    private function imageExtension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
