<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Dossier;
use App\Models\ExportLog;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function __construct(private DossierService $dossiers) {}

    public function history(AdminUser $admin): array
    {
        return ExportLog::query()
            ->with('adminUser')
            ->where('admin_user_id', $admin->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (ExportLog $log) => [
                'id' => $log->id,
                'nom' => $log->nom,
                'format' => $log->format,
                'par' => $log->adminUser->display_name,
                'date' => $log->created_at->format('d/m/Y H:i'),
            ])
            ->all();
    }

    public function log(AdminUser $admin, string $nom, string $format): ExportLog
    {
        return ExportLog::query()->create([
            'admin_user_id' => $admin->id,
            'nom' => $nom,
            'format' => $format,
        ]);
    }

    public function exportMembres(AdminUser $admin, string $format = 'CSV'): StreamedResponse
    {
        $rows = Dossier::query()
            ->with('assure')
            ->where('statut', '!=', 'Brouillon')
            ->orderByDesc('date_soumission')
            ->get();

        $headers = ['Référence', 'Assuré', 'Matricule', 'Bénéficiaire', 'Lien', 'Statut', 'Soumis le', 'Gestionnaire'];
        $data = $rows->map(fn (Dossier $d) => [
            $d->ref,
            $d->assure->full_name,
            $d->assure->matricule,
            $d->beneficiaire,
            $d->lien,
            $d->statut,
            $d->date_soumission?->format('d/m/Y') ?? '',
            $d->gestionnaire ?? 'Non affecté',
        ]);

        $this->log($admin, 'Membres enrôlés', $format);

        return $this->streamCsv($headers, $data, 'membres-enroles', $format);
    }

    public function exportDossiers(AdminUser $admin, string $format = 'CSV'): StreamedResponse
    {
        $rows = Dossier::query()
            ->with('assure')
            ->where('statut', '!=', 'Brouillon')
            ->orderByDesc('date_soumission')
            ->get();

        $headers = ['Référence', 'Assuré principal', 'Membre', 'Statut', 'Date soumission', 'Date décision', 'Motif refus', 'Gestionnaire'];
        $data = $rows->map(fn (Dossier $d) => [
            $d->ref,
            $d->assure->full_name,
            $d->beneficiaire,
            $d->statut,
            $d->date_soumission?->format('d/m/Y') ?? '',
            $d->date_decision?->format('d/m/Y') ?? '',
            $d->motif_refus ?? '',
            $d->gestionnaire ?? 'Non affecté',
        ]);

        $this->log($admin, 'Dossiers d\'enrôlement', $format);

        return $this->streamCsv($headers, $data, 'dossiers-enrolement', $format);
    }

    public function dossierForPdf(string $ref): ?array
    {
        $dossier = Dossier::query()->with('assure')->where('ref', $ref)->first();
        if (! $dossier) {
            return null;
        }

        $membre = $this->dossiers->formatMembre($dossier);
        $assure = $dossier->assure;

        return [
            'membre' => $membre,
            'assure' => [
                'nom' => $assure->nom,
                'prenom' => $assure->prenom,
                'fullName' => $assure->full_name,
                'matricule' => $assure->matricule,
                'numeroCama' => $assure->numero_cama,
                'grade' => $assure->grade,
                'armee' => $assure->armee,
            ],
        ];
    }

    private function streamCsv(array $headers, $rows, string $basename, string $format): StreamedResponse
    {
        $ext = strtolower($format) === 'excel' ? 'xlsx' : 'csv';
        $mime = $ext === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, "{$basename}-".now()->format('Y-m-d').".{$ext}", [
            'Content-Type' => $mime,
        ]);
    }
}
