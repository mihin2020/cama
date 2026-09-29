<?php

namespace App\Services;

use App\Models\Assure;
use App\Models\AssureNotification;
use App\Models\Dossier;

class HistoriqueService
{
    public function eventsFor(Assure $assure): array
    {
        $events = [];

        foreach ($assure->journal ?? [] as $entry) {
            $events[] = [
                'date' => $entry['date'] ?? '',
                'libelle' => $entry['libelle'] ?? '',
                'cat' => 'Compte',
                'membre' => null,
            ];
        }

        foreach ($assure->dossiers as $dossier) {
            foreach ($dossier->journal ?? [] as $entry) {
                $libelle = $entry['libelle'] ?? '';
                $events[] = [
                    'date' => $entry['date'] ?? '',
                    'libelle' => $libelle,
                    'cat' => $this->categorize($libelle),
                    'membre' => $dossier->beneficiaire,
                    'ref' => $dossier->ref,
                ];
            }
        }

        usort($events, fn ($a, $b) => $this->parseFrDate($b['date']) <=> $this->parseFrDate($a['date']));

        return $events;
    }

    private function categorize(string $libelle): string
    {
        $l = mb_strtolower($libelle);
        if (str_contains($l, 'brouillon') || str_contains($l, 'membre ajouté')) {
            return 'Membres';
        }

        return 'Dossiers';
    }

    private function parseFrDate(string $dateStr): int
    {
        $datePart = explode(' ', $dateStr)[0] ?? '';
        $parts = explode('/', $datePart);
        if (count($parts) !== 3) {
            return 0;
        }

        return (int) ($parts[2].$parts[1].$parts[0]);
    }
}
