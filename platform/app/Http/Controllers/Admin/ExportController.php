<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(ExportService $service): Response
    {
        return Inertia::render('Admin/Exports/Index', [
            'history' => $service->history(auth('admin')->user()),
        ]);
    }

    public function membres(Request $request, ExportService $service): StreamedResponse
    {
        $format = $request->string('format', 'CSV')->toString();

        return $service->exportMembres(auth('admin')->user(), $format);
    }

    public function dossiers(Request $request, ExportService $service): StreamedResponse
    {
        $format = $request->string('format', 'CSV')->toString();

        return $service->exportDossiers(auth('admin')->user(), $format);
    }

    public function dossierPdf(Request $request, ExportService $service): JsonResponse
    {
        $data = $request->validate(['ref' => ['required', 'string', 'max:50']]);
        $payload = $service->dossierForPdf($data['ref']);

        if (! $payload) {
            return response()->json(['error' => 'Aucun dossier ne correspond à cette référence.'], 404);
        }

        $service->log(auth('admin')->user(), "Fiche dossier {$data['ref']}", 'PDF');

        return response()->json($payload);
    }
}
