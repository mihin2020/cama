<?php

namespace App\Services;

use App\Models\Assure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DossierPieceStorageService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public function storeLotPiece(Assure $assure, UploadedFile $file, string $type): array
    {
        $this->assertValidUpload($file);

        $stored = $file->store("dossiers/{$assure->id}/lot", 'local');

        return [
            'type' => $type,
            'statut' => 'Soumise',
            'filename' => $file->getClientOriginalName(),
            'path' => $stored,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'lot' => true,
        ];
    }

    /**
     * Stocke une pièce justificative d'un membre (conjoint/enfant) et retourne ses métadonnées.
     *
     * @return array<string, mixed>
     */
    public function storeMemberPiece(Assure $assure, UploadedFile $file, string $key, string $type): array
    {
        $this->assertValidUpload($file, "piece.{$key}");

        $stored = $file->store("dossiers/{$assure->id}/membres", 'local');

        return [
            'key' => $key,
            'type' => $type,
            'statut' => 'Soumise',
            'filename' => $file->getClientOriginalName(),
            'path' => $stored,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    public function diskPath(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }

    private function assertValidUpload(UploadedFile $file, string $field = 'fif_signee'): void
    {
        if (! in_array($file->getMimeType(), self::MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                $field => 'Format non accepté (PDF, JPEG ou PNG).',
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                $field => 'Fichier trop volumineux (5 Mo maximum).',
            ]);
        }
    }
}
